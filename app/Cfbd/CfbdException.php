<?php

declare(strict_types=1);

namespace App\Cfbd;

use RuntimeException;

final class CfbdException extends RuntimeException
{
    public static function missingKey(): self
    {
        return new self('CFBD_API_KEY is not configured.');
    }

    public static function requestFailed(string $endpoint, int $status, string $body): self
    {
        return new self("CFBD request to [{$endpoint}] failed with HTTP {$status}: ".mb_substr($body, 0, 200));
    }

    public static function unexpectedResponse(string $endpoint): self
    {
        return new self("CFBD returned an unexpected response body for [{$endpoint}].");
    }
}
