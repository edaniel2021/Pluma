<?php

namespace App\Domain\Posts\Enums;

enum PostState: string
{
    case Draft = 'draft';
    case Queue = 'queue';
    case Published = 'published';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Queue => 'Queued',
            self::Published => 'Published',
            self::Error => 'Error',
        };
    }
}
