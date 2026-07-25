<?php

namespace App\Domain\Integrations\Support;

use RuntimeException;

/**
 * The platform rejected the access token (typically a 401). PublishPostJob
 * treats this distinctly from BadBodyException so it can eventually trigger
 * a refresh-then-retry instead of just failing.
 */
class RefreshTokenException extends RuntimeException
{
}
