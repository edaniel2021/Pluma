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
    // ChatCompletionContract to the matching service). Independent of
    // image generation below, which is still FAL/OpenAI-Images only.
    'chat_provider' => env('AI_CHAT_PROVIDER', 'openai'),

    // Chat model used for the tool-calling conversation loop - see
    // App\Domain\Agents\Support\AgentConversationService.
    'model' => env('OPENAI_AGENT_MODEL', 'gpt-4o-mini'),

    // Gemini equivalents of the above two - only used when chat_provider
    // is 'gemini'. Model IDs use Google's own naming (not date-versioned
    // like LinkedIn's API), so this doesn't have the same "current month
    // isn't active yet" trap LinkedInProvider hit.
    'gemini_model' => env('GEMINI_AGENT_MODEL', 'gemini-3.6-flash'),
    'gemini_api_key' => env('GEMINI_API_KEY'),

    // Image-generation model used when FAL_KEY isn't set (GenerateImageTool
    // falls back to OpenAI's Images API in that case).
    'openai_image_model' => env('OPENAI_IMAGE_MODEL', 'dall-e-3'),

    // FAL.ai model path (appended to https://fal.run/) used for image
    // generation when FAL_KEY is configured.
    'fal_image_model' => env('FAL_IMAGE_MODEL', 'fal-ai/flux/dev'),

    // Safety cap on the tool-call -> tool-result -> re-prompt loop per user
    // message, so a misbehaving model can't loop forever inside the queued job.
    'max_tool_iterations' => 5,

];
