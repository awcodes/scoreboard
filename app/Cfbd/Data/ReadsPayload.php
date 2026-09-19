<?php

declare(strict_types=1);

namespace App\Cfbd\Data;

use App\Cfbd\InvalidPayload;
use Illuminate\Support\Carbon;
use Throwable;

trait ReadsPayload
{
    private static function required(array $payload, string $key): mixed
    {
        $value = data_get($payload, $key);

        if ($value === null || $value === '') {
            throw InvalidPayload::missing(class_basename(static::class), $key, $payload);
        }

        return $value;
    }

    private static function string(array $payload, string $key): ?string
    {
        $value = data_get($payload, $key);

        return is_string($value) && mb_trim($value) !== '' ? mb_trim($value) : null;
    }

    private static function int(array $payload, string $key): ?int
    {
        $value = data_get($payload, $key);

        return is_numeric($value) ? (int) $value : null;
    }

    private static function date(array $payload, string $key): Carbon
    {
        $value = self::required($payload, $key);

        try {
            return Carbon::parse($value)->utc();
        } catch (Throwable) {
            throw InvalidPayload::missing(class_basename(static::class), $key, $payload);
        }
    }

    /**
     * CFBD mixes cases ("FBS", "fbs"); normalize so comparisons are reliable.
     */
    private static function classification(array $payload, string $key): ?string
    {
        $value = self::string($payload, $key);

        return $value === null ? null : mb_strtolower($value);
    }
}
