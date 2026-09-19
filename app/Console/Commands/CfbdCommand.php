<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Cfbd\CfbdException;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Shared handling for sync commands: an upstream failure is logged and
 * reported, and local data is left untouched.
 */
abstract class CfbdCommand extends Command
{
    protected function attempt(Closure $callback): int
    {
        try {
            $callback();
        } catch (CfbdException $e) {
            Log::error("{$this->getName()}: {$e->getMessage()}");
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
