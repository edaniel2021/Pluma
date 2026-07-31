<?php

namespace App\Domain\Agents\Enums;

enum AgentMessageRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
