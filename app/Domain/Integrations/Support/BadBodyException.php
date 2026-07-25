<?php

namespace App\Domain\Integrations\Support;

use RuntimeException;

/**
 * The platform rejected the request itself (bad content, rate limited,
 * validation error, etc.) - anything that isn't a token problem.
 */
class BadBodyException extends RuntimeException
{
}
