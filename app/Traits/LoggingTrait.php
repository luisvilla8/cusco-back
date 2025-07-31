<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait LoggingTrait
{
    /**
     * Get context for logging
     */
    protected function getLogContext(): array
    {
        return [
            'service' => static::class,
            'user_id' => auth()->id() ?? null,
            'ip' => request()->ip() ?? null,
            'url' => request()->fullUrl() ?? null,
            'method' => request()->method() ?? null,
        ];
    }

    /**
     * Log info with context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info($message, array_merge($context, $this->getLogContext()));
    }

    /**
     * Log warning with context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning($message, array_merge($context, $this->getLogContext()));
    }

    /**
     * Log error with context
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error($message, array_merge($context, $this->getLogContext()));
    }
}