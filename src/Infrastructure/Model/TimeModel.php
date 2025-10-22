<?php

declare(strict_types=1);

namespace App\Infrastructure\Model;

use App\Domain\Driven\AiModelInterface;
use App\Domain\Driven\Attribute\Intent;

#[Intent('time', aliases: ['clock', 'hour', 'current time'], description: 'Tells the current time.')]
final class TimeModel implements AiModelInterface
{
    public function handle(string $message, array $context = []): string
    {
        $tz = $context['timezone'] ?? 'UTC';
        $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));

        return sprintf('🕒 The current time in %s is %s', $tz, $now->format('H:i:s'));
    }

    public function handleStream(string $message, array $context = []): iterable
    {
        $tz = $context['timezone'] ?? 'UTC';

        yield ['type' => 'status', 'text' => "Checking time for {$tz}..."];
        usleep(300000);

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone($tz));
            yield ['type' => 'chunk', 'text' => sprintf("✅ It's %s", $now->format('H:i:s'))];
            yield ['type' => 'done'];
        } catch (\Throwable $e) {
            yield ['type' => 'error', 'text' => 'Failed to retrieve time', 'data' => $e->getMessage()];
        }
    }
}
