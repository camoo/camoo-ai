<?php

declare(strict_types=1);

namespace App\Domain\Driven;

/**
 * Technology-agnostic AI model contract.
 *
 * Supports both synchronous and streaming operation.
 */
interface AiModelInterface
{
    /**
     * Handles a message and returns a single response string.
     *
     * @param array<string,mixed> $context
     */
    public function handle(string $message, array $context = []): string;

    /**
     * Handles a message as a sequence of structured events.
     *
     * Each yielded event is an associative array like:
     * [
     *   'type' => 'chunk'|'status'|'error'|'done',
     *   'text' => 'string (optional)',
     *   'data' => mixed (optional payload)
     * ]
     *
     * @param array<string,mixed> $context
     *
     * @return iterable<array<string,mixed>>
     */
    public function handleStream(string $message, array $context = []): iterable;
}
