<?php

namespace Tests\Feature;

use App\Domain\Agents\Enums\AgentMessageRole;
use App\Domain\Agents\Jobs\ProcessAgentMessageJob;
use App\Domain\Agents\Models\AgentThread;
use App\Domain\Agents\Support\AgentConversationService;
use App\Domain\Agents\Support\FalService;
use App\Domain\Agents\Support\GeminiService;
use App\Domain\Agents\Tools\GenerateImageTool;
use App\Domain\Agents\Tools\ListIntegrationsTool;
use App\Domain\Agents\Tools\SchedulePostTool;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Organization\Support\CurrentOrganization;
use App\Domain\Posts\Models\Post;
use App\Livewire\Agents\Chat;
use App\Livewire\Agents\Threads;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class AgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_thread_can_be_created_via_livewire(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);

        Livewire::test(Threads::class)->call('create');

        $thread = AgentThread::first();

        $this->assertNotNull($thread);
        $this->assertSame($user->currentTeam->id, $thread->organization_id);
        $this->assertSame($user->id, $thread->user_id);
    }

    public function test_organizations_only_see_their_own_agent_threads(): void
    {
        $userA = User::factory()->withPersonalTeam()->create();
        $this->actingAs($userA);
        $userA->currentTeam->agentThreads()->create(['title' => 'Thread A']);

        $userB = User::factory()->withPersonalTeam()->create();
        $this->actingAs($userB);
        $userB->currentTeam->agentThreads()->create(['title' => 'Thread B']);

        $this->actingAs($userA);

        Livewire::test(Threads::class)
            ->assertSee('Thread A')
            ->assertDontSee('Thread B');
    }

    public function test_sending_a_message_persists_it_and_dispatches_the_job(): void
    {
        Queue::fake();

        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $thread = $user->currentTeam->agentThreads()->create([]);

        Livewire::test(Chat::class, ['thread' => $thread])
            ->set('content', 'Schedule a post for LinkedIn tomorrow')
            ->call('send');

        $message = $thread->messages()->first();

        $this->assertNotNull($message);
        $this->assertSame(AgentMessageRole::User, $message->role);
        $this->assertSame('Schedule a post for LinkedIn tomorrow', $message->content);

        Queue::assertPushed(ProcessAgentMessageJob::class, fn ($job) => $job->threadId === $thread->id);
    }

    public function test_chat_renders_the_generated_image_inline_when_generate_image_succeeds(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $thread = $user->currentTeam->agentThreads()->create([]);
        $thread->messages()->create([
            'role' => AgentMessageRole::Tool,
            'tool_name' => 'generate_image',
            'tool_call_id' => 'call_1',
            'content' => json_encode(['media_id' => 1, 'url' => 'https://example.com/storage/1/wheelchair.png']),
        ]);

        Livewire::test(Chat::class, ['thread' => $thread])
            ->assertSee('https://example.com/storage/1/wheelchair.png', false);
    }

    public function test_chat_does_not_render_an_image_when_generate_image_fails(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $this->actingAs($user);
        $thread = $user->currentTeam->agentThreads()->create([]);
        $thread->messages()->create([
            'role' => AgentMessageRole::Tool,
            'tool_name' => 'generate_image',
            'tool_call_id' => 'call_1',
            'content' => json_encode(['error' => 'OpenAI API key is missing.']),
        ]);

        Livewire::test(Chat::class, ['thread' => $thread])
            ->assertDontSee('<img', false);
    }

    public function test_the_conversation_service_persists_a_plain_assistant_reply(): void
    {
        OpenAI::fake([
            CreateResponse::fake(),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);
        $thread = $organization->agentThreads()->create(['user_id' => $user->id]);
        $thread->messages()->create(['role' => AgentMessageRole::User, 'content' => 'Hello']);

        app(AgentConversationService::class)->respond($thread);

        $reply = $thread->messages()->latest('id')->first();

        $this->assertSame(AgentMessageRole::Assistant, $reply->role);
        $this->assertStringContainsString('fake chat response', $reply->content);

        CurrentOrganization::clear();
    }

    public function test_the_conversation_service_executes_a_tool_call_before_replying(): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'message' => [
                        'content' => null,
                        'tool_calls' => [[
                            'id' => 'call_1',
                            'type' => 'function',
                            'function' => ['name' => 'list_channels', 'arguments' => '{}'],
                        ]],
                    ],
                ]],
            ]),
            CreateResponse::fake([
                'choices' => [[
                    'message' => ['content' => 'You have one channel connected.'],
                ]],
            ]),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);
        Integration::factory()->create(['organization_id' => $organization->id]);
        $thread = $organization->agentThreads()->create(['user_id' => $user->id]);
        $thread->messages()->create(['role' => AgentMessageRole::User, 'content' => 'What channels do I have?']);

        app(AgentConversationService::class)->respond($thread);

        $messages = $thread->messages()->orderBy('id')->get();

        $toolMessage = $messages->firstWhere('role', AgentMessageRole::Tool);
        $this->assertNotNull($toolMessage);
        $this->assertSame('list_channels', $toolMessage->tool_name);
        $this->assertSame('call_1', $toolMessage->tool_call_id);
        $this->assertStringContainsString('"channels"', $toolMessage->content);

        $finalReply = $messages->last();
        $this->assertSame(AgentMessageRole::Assistant, $finalReply->role);
        $this->assertSame('You have one channel connected.', $finalReply->content);

        OpenAI::assertSent(\OpenAI\Resources\Chat::class, 2);

        CurrentOrganization::clear();
    }

    /**
     * Regression test for a real staging incident: the model called
     * list_channels 5 times in a row and never progressed to
     * generate_image/schedule_post. Root cause was buildMessages() reading
     * $thread->messages (the cached relation property) instead of a fresh
     * query - $thread->messages()->create() (used by respond()'s loop each
     * iteration) does not update an already-loaded relation cache, so every
     * iteration after the first saw the exact same stale snapshot from
     * before the loop started, missing its own prior tool calls entirely.
     * Reproduces that exact caching gap directly rather than orchestrating
     * a multi-round fake exchange.
     */
    public function test_build_messages_reflects_messages_created_after_the_relation_was_first_cached(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);
        $thread = $organization->agentThreads()->create(['user_id' => $user->id]);
        $thread->messages()->create(['role' => AgentMessageRole::User, 'content' => 'hi']);

        // Forces the relation to cache at this point, exactly as the loop's
        // first buildMessages() call would.
        $thread->messages;

        // What a later loop iteration does: create via the relation method,
        // not the cached property.
        $thread->messages()->create(['role' => AgentMessageRole::Assistant, 'content' => 'a later reply']);

        $method = new \ReflectionMethod(AgentConversationService::class, 'buildMessages');
        $messages = $method->invoke(app(AgentConversationService::class), $thread, $organization);

        $this->assertTrue(
            collect($messages)->contains(fn ($m) => ($m['content'] ?? null) === 'a later reply'),
            'buildMessages() did not see a message created after the relation was already cached.'
        );

        CurrentOrganization::clear();
    }

    public function test_tool_definitions_stop_offering_list_channels_once_it_has_already_succeeded(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);
        $thread = $organization->agentThreads()->create(['user_id' => $user->id]);
        $thread->messages()->create(['role' => AgentMessageRole::User, 'content' => 'hi']);
        $thread->messages()->create([
            'role' => AgentMessageRole::Tool,
            'tool_name' => 'list_channels',
            'tool_call_id' => 'call_1',
            'content' => '{"channels":[]}',
        ]);

        $method = new \ReflectionMethod(AgentConversationService::class, 'toolDefinitions');
        $tools = $method->invoke(app(AgentConversationService::class), $thread);

        $toolNames = collect($tools)->pluck('function.name')->all();

        $this->assertNotContains('list_channels', $toolNames);
        $this->assertContains('generate_image', $toolNames);
        $this->assertContains('schedule_post', $toolNames);

        // Regression: reject() preserves original array keys, so removing
        // the first tool left the rest keyed at 1, 2, ... instead of
        // 0, 1, ... - json_encode() treats a non-sequential-from-zero array
        // as a JSON object, not an array, and the real API rejected the
        // whole request over it ("Invalid type for 'tools': expected an
        // array of objects, but got an object instead"). Checking tool
        // names alone (above) doesn't catch this - array_is_list() is the
        // actual property that matters here.
        $this->assertTrue(array_is_list($tools));

        CurrentOrganization::clear();
    }

    public function test_gemini_service_translates_messages_and_tools_and_parses_the_response(): void
    {
        config(['agents.gemini_api_key' => 'test-gemini-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [
                            ['text' => 'Here is your answer.'],
                            ['functionCall' => ['id' => 'call_1', 'name' => 'list_channels', 'args' => ['foo' => 'bar']]],
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'What channels do I have?'],
            [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_0',
                    'type' => 'function',
                    'function' => ['name' => 'list_channels', 'arguments' => '{}'],
                ]],
            ],
            [
                'role' => 'tool',
                'tool_call_id' => 'call_0',
                'tool_name' => 'list_channels',
                'content' => '{"channels":[]}',
            ],
        ];

        $tools = [[
            'type' => 'function',
            'function' => [
                'name' => 'list_channels',
                'description' => 'Lists channels',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ]];

        $result = app(GeminiService::class)->chat($messages, $tools);

        $this->assertSame('Here is your answer.', $result->content);
        $this->assertCount(1, $result->toolCalls);
        $this->assertSame('call_1', $result->toolCalls[0]->id);
        $this->assertSame('list_channels', $result->toolCalls[0]->name);
        $this->assertSame(['foo' => 'bar'], json_decode($result->toolCalls[0]->argumentsJson, true));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->hasHeader('x-goog-api-key', 'test-gemini-key')
                && $body['systemInstruction']['parts'][0]['text'] === 'You are a helpful assistant.'
                && $body['contents'][0] === ['role' => 'user', 'parts' => [['text' => 'What channels do I have?']]]
                && $body['contents'][1]['role'] === 'model'
                && $body['contents'][1]['parts'][0]['functionCall']['name'] === 'list_channels'
                && $body['contents'][2]['role'] === 'user'
                && $body['contents'][2]['parts'][0]['functionResponse']['name'] === 'list_channels'
                && $body['contents'][2]['parts'][0]['functionResponse']['id'] === 'call_0'
                && $body['tools'][0]['functionDeclarations'][0]['name'] === 'list_channels';
        });
    }

    /**
     * Regression test for a real Gemini 400 hit via the AI Assistant chat:
     * "Invalid JSON payload received... Proto field is not repeating,
     * cannot start list" - Gemini's args/response fields are object-typed
     * (Struct), but json_decode('{}', true) and json_decode('[]', true)
     * both produce the same empty PHP array, and json_encode([]) always
     * renders `[]`, never `{}`. A no-argument tool call (list_channels())
     * or a no-result tool response therefore silently sent the wrong JSON
     * shape. Http::fake()'s decoded assertSent() body can't tell `{}` from
     * `[]` either (same PHP-array collapse), so this checks the raw
     * request body string instead.
     */
    public function test_gemini_service_sends_empty_tool_arguments_as_a_json_object_not_an_array(): void
    {
        config(['agents.gemini_api_key' => 'test-gemini-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]],
            ], 200),
        ]);

        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'What channels do I have?'],
            [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'id' => 'call_0',
                    'type' => 'function',
                    'function' => ['name' => 'list_channels', 'arguments' => '{}'],
                ]],
            ],
            [
                'role' => 'tool',
                'tool_call_id' => 'call_0',
                'tool_name' => 'list_channels',
                'content' => '{}',
            ],
        ];

        app(GeminiService::class)->chat($messages);

        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($body, '"args":{}')
                && str_contains($body, '"response":{}')
                && ! str_contains($body, '"args":[]')
                && ! str_contains($body, '"response":[]');
        });
    }

    public function test_the_conversation_service_uses_gemini_when_configured(): void
    {
        config(['agents.chat_provider' => 'gemini', 'agents.gemini_api_key' => 'test-gemini-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [
                                ['functionCall' => ['id' => 'call_1', 'name' => 'list_channels', 'args' => []]],
                            ],
                        ],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => ['parts' => [['text' => 'You have one channel connected.']]],
                    ]],
                ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);
        Integration::factory()->create(['organization_id' => $organization->id]);
        $thread = $organization->agentThreads()->create(['user_id' => $user->id]);
        $thread->messages()->create(['role' => AgentMessageRole::User, 'content' => 'What channels do I have?']);

        app(AgentConversationService::class)->respond($thread);

        $messages = $thread->messages()->orderBy('id')->get();

        $toolMessage = $messages->firstWhere('role', AgentMessageRole::Tool);
        $this->assertNotNull($toolMessage);
        $this->assertSame('list_channels', $toolMessage->tool_name);
        $this->assertSame('call_1', $toolMessage->tool_call_id);

        $finalReply = $messages->last();
        $this->assertSame(AgentMessageRole::Assistant, $finalReply->role);
        $this->assertSame('You have one channel connected.', $finalReply->content);

        CurrentOrganization::clear();
    }

    public function test_the_job_records_a_visible_error_message_on_permanent_failure(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        CurrentOrganization::set($user->currentTeam);
        $thread = $user->currentTeam->agentThreads()->create(['user_id' => $user->id]);
        CurrentOrganization::clear();

        (new ProcessAgentMessageJob($thread->id))->failed(new \Exception('OpenAI API key is missing.'));

        $reply = $thread->messages()->latest('id')->first();

        $this->assertSame(AgentMessageRole::Assistant, $reply->role);
        $this->assertStringContainsString('OpenAI API key is missing.', $reply->content);
    }

    public function test_list_integrations_tool_returns_connected_channels(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);
        Integration::factory()->create(['organization_id' => $organization->id, 'provider' => 'linkedin']);

        $result = app(ListIntegrationsTool::class)->handle([], $organization->fresh(), $user);

        $this->assertCount(1, $result['channels']);
        $this->assertSame('linkedin', $result['channels'][0]['platform']);

        CurrentOrganization::clear();
    }

    public function test_schedule_post_tool_creates_a_draft_post(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);
        $integration = Integration::factory()->create(['organization_id' => $organization->id]);

        $result = app(SchedulePostTool::class)->handle([
            'integration_id' => $integration->id,
            'content' => 'Hello world',
            'state' => 'draft',
        ], $organization->fresh(), $user);

        $post = Post::find($result['post_id']);

        $this->assertNotNull($post);
        $this->assertSame($user->id, $post->user_id);
        $this->assertSame($integration->id, $post->integration_id);
        $this->assertSame('Hello world', $post->content);
        $this->assertSame('draft', $post->state->value);

        CurrentOrganization::clear();
    }

    public function test_schedule_post_tool_requires_scheduled_at_when_queueing(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);
        $integration = Integration::factory()->create(['organization_id' => $organization->id]);

        $result = app(SchedulePostTool::class)->handle([
            'integration_id' => $integration->id,
            'content' => 'Hello world',
            'state' => 'queue',
        ], $organization->fresh(), $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, Post::count());

        CurrentOrganization::clear();
    }

    public function test_generate_image_tool_falls_back_to_openai_when_fal_is_not_configured(): void
    {
        config(['services.fal.key' => null]);

        OpenAI::fake([
            \OpenAI\Responses\Images\CreateResponse::fake([
                'data' => [['b64_json' => base64_encode('fake-png-bytes')]],
            ]),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);

        $result = app(GenerateImageTool::class)->handle(['prompt' => 'a red bicycle'], $organization->fresh(), $user);

        $this->assertArrayHasKey('media_id', $result);
        $this->assertNotNull($organization->fresh()->getMedia('library')->firstWhere('id', $result['media_id']));

        CurrentOrganization::clear();
    }

    /**
     * Regression test for a real staging failure: models tend to write
     * full descriptive paragraphs as image prompts (not short captions),
     * and nothing bounded that length before it was used verbatim as
     * media.name - a varchar(255) column. A ~260-character prompt failed
     * the whole insert with "value too long for type character
     * varying(255)".
     */
    public function test_generate_image_tool_truncates_a_long_prompt_for_the_media_name(): void
    {
        config(['services.fal.key' => null]);

        OpenAI::fake([
            \OpenAI\Responses\Images\CreateResponse::fake([
                'data' => [['b64_json' => base64_encode('fake-png-bytes')]],
            ]),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);

        $longPrompt = str_repeat('a wheelchair with a sleek design and vibrant colors, ', 6);
        $this->assertGreaterThan(255, strlen($longPrompt));

        $result = app(GenerateImageTool::class)->handle(['prompt' => $longPrompt], $organization->fresh(), $user);

        $this->assertArrayHasKey('media_id', $result);
        $media = $organization->fresh()->getMedia('library')->firstWhere('id', $result['media_id']);
        $this->assertNotNull($media);
        $this->assertLessThanOrEqual(255, strlen($media->name));

        CurrentOrganization::clear();
    }

    public function test_generate_image_tool_uses_gemini_when_that_is_the_chat_provider_and_fal_is_not_configured(): void
    {
        config(['services.fal.key' => null, 'agents.chat_provider' => 'gemini', 'agents.gemini_api_key' => 'test-gemini-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [
                            ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('fake-png-bytes')]],
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $user = User::factory()->withPersonalTeam()->create();
        $organization = $user->currentTeam;
        CurrentOrganization::set($organization);

        $result = app(GenerateImageTool::class)->handle(['prompt' => 'a red bicycle'], $organization->fresh(), $user);

        $this->assertArrayHasKey('media_id', $result);
        $this->assertNotNull($organization->fresh()->getMedia('library')->firstWhere('id', $result['media_id']));

        Http::assertSent(fn ($request) => str($request->url())->contains('gemini-2.5-flash-image')
            && $request->hasHeader('x-goog-api-key', 'test-gemini-key'));

        CurrentOrganization::clear();
    }

    /**
     * Exercises GeminiService directly rather than through GenerateImageTool
     * (same reasoning as the FalService test below it) - confirms the
     * request shape (image-capable model, responseModalities) and that the
     * inlineData part's base64 payload is extracted correctly.
     */
    public function test_gemini_service_generates_an_image_via_http(): void
    {
        config(['agents.gemini_api_key' => 'test-gemini-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [
                            ['text' => "Here's your image:"],
                            ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode('fake-png-bytes')]],
                        ],
                    ],
                ]],
            ], 200),
        ]);

        $imageData = app(GeminiService::class)->generateImage('a red bicycle');

        $this->assertSame(base64_encode('fake-png-bytes'), $imageData);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return str($request->url())->contains('gemini-2.5-flash-image')
                && $body['contents'][0]['parts'][0]['text'] === 'a red bicycle'
                && $body['generationConfig']['responseModalities'] === ['TEXT', 'IMAGE'];
        });
    }

    /**
     * Exercises FalService directly rather than through GenerateImageTool -
     * the tool's FAL branch calls addMediaFromUrl(), which downloads via
     * spatie's own downloader (not the Http facade), so it can't be faked
     * at this layer without a real network fetch.
     */
    public function test_fal_service_generates_an_image_via_http(): void
    {
        config(['services.fal.key' => 'test-fal-key']);

        Http::fake([
            'fal.run/*' => Http::response(['images' => [['url' => 'https://cdn.fal.ai/fake.png']]], 200),
        ]);

        $url = app(FalService::class)->generateImage('a red bicycle');

        $this->assertSame('https://cdn.fal.ai/fake.png', $url);
        Http::assertSent(fn ($request) => str($request->url())->contains('fal.run')
            && $request->hasHeader('Authorization', 'Key test-fal-key'));
    }

    public function test_fal_service_reports_whether_it_is_configured(): void
    {
        config(['services.fal.key' => null]);
        $this->assertFalse(app(FalService::class)->isConfigured());

        config(['services.fal.key' => 'test-fal-key']);
        $this->assertTrue(app(FalService::class)->isConfigured());
    }

    /**
     * Real production bug: "cURL error 28: Operation timed out after 30002
     * milliseconds" calling OpenAI's gpt-image-1 image-generation endpoint -
     * the openai-php/laravel package's default request_timeout (30s) is too
     * short for it. Fixing that alone isn't enough though: the HTTP timeout,
     * the job's own $timeout, the WithoutOverlapping lock's expiresAfter,
     * and the Redis queue's retry_after all have to nest in ascending order,
     * or the job either gets killed by Horizon before the HTTP call can
     * finish, or gets requeued onto a second worker while still legitimately
     * running on the first. This asserts that chain stays intact regardless
     * of what any individual value gets tuned to later.
     */
    public function test_timeout_values_nest_correctly_for_slow_image_generation(): void
    {
        $httpTimeout = config('openai.request_timeout');
        $job = new ProcessAgentMessageJob(1);
        $jobTimeout = $job->timeout;
        $lockExpiresAfter = $job->middleware()[0]->expiresAfter;
        $queueRetryAfter = config('queue.connections.redis.retry_after');

        $this->assertGreaterThan($httpTimeout, $jobTimeout,
            "job timeout ({$jobTimeout}s) must exceed the OpenAI HTTP timeout ({$httpTimeout}s) or Horizon kills the job before the HTTP call can ever succeed");
        $this->assertGreaterThan($jobTimeout, $lockExpiresAfter,
            "WithoutOverlapping's expiresAfter ({$lockExpiresAfter}s) must exceed the job timeout ({$jobTimeout}s) or the lock can expire mid-run and let a duplicate job start");
        $this->assertGreaterThan($jobTimeout, $queueRetryAfter,
            "queue retry_after ({$queueRetryAfter}s) must exceed the job timeout ({$jobTimeout}s) or a still-running job can get requeued onto a second worker");
    }
}
