<?php

declare(strict_types=1);

namespace App\Cfbd;

use InvalidArgumentException;

/**
 * A single upstream record was missing required data. Sync skips the record
 * and keeps going rather than failing the whole batch.
 */
final class InvalidPayload extends InvalidArgumentException
{
    public static function missing(string $type, string $key, array $payload): self
    {
        $id = $payload['id'] ?? $payload['teamId'] ?? '?';

        return new self("{$type} payload [{$id}] is missing [{$key}].");
    }
}
