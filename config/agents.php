<?php

use App\Domain\Agents\Tools\GenerateImageTool;
use App\Domain\Agents\Tools\ListIntegrationsTool;
use App\Domain\Agents\Tools\SchedulePostTool;

return [

    // Registered tools available to the conversation loop - the extensibility
    // point for adding more (e.g. analytics, WhatsApp broadcasts) later,
    // mirroring config/social-providers.php's registry pattern.
    'tools' => [
        ListIntegrationsTool::class,
        GenerateImageTool::class,
        SchedulePostTool::class,
    ],

    // Which provider backs the tool-calling chat loop - 'openai' or
    // 'gemini' (see AppServiceProvider::register(), which binds
    // ChatCompletionContract to the matching service).
    'chat_provider' => env('AI_CHAT_PROVIDER', 'openai'),

    // Chat model used for the tool-calling conversation loop - see
    // App\Domain\Agents\Support\AgentConversationService.
    'model' => env('OPENAI_AGENT_MODEL', 'gpt-4o-mini'),

    // Gemini equivalents of the above two - only used when chat_provider
    // is 'gemini'. Model IDs use Google's own naming (not date-versioned
    // like LinkedIn's API), so this doesn't have the same "current month
    // isn't active yet" trap LinkedInProvider hit.
    //
    // Deliberately gemini-2.5-flash, not the newer gemini-3.6-flash: the
    // free tier caps the newest model at just 20 requests/day (hit for
    // real during dev testing - a single chat turn with a couple of tool
    // calls burns several of those), versus 250/day for this one. Same
    // "fast, cheap" capability tier either way for a tool-calling agent -
    // not worth the newer model's much tighter free quota here.
    'gemini_model' => env('GEMINI_AGENT_MODEL', 'gemini-2.5-flash'),
    'gemini_api_key' => env('GEMINI_API_KEY'),

    // Image-generation priority (see GenerateImageTool): FAL first if
    // FAL_KEY is set (generally cheaper/faster), then Gemini if
    // chat_provider is 'gemini' (avoids needing an OpenAI/FAL key at all
    // in a Gemini-centric setup), then OpenAI's Images API as the final
    // fallback.
    //
    // gpt-image-1, not dall-e-3: DALL-E-2/3 were both shut down entirely
    // on 2026-05-12. gpt-image-1 is the successor and has a different
    // request shape (see OpenAiService::generateImage()'s comment).
    'openai_image_model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),

    // FAL.ai model path (appended to https://fal.run/) used for image
    // generation when FAL_KEY is configured.
    'fal_image_model' => env('FAL_IMAGE_MODEL', 'fal-ai/flux/dev'),

    // Gemini's native image-generation model ("Nano Banana" family) - not
    // the separate Imagen API, which is deprecated (shutting down
    // 2026-08-17). See the priority note above for when this gets used.
    'gemini_image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),

    // Safety cap on the tool-call -> tool-result -> re-prompt loop per user
    // message, so a misbehaving model can't loop forever inside the queued job.
    'max_tool_iterations' => 5,

];
