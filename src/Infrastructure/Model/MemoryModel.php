<?php

declare(strict_types=1);

namespace App\Infrastructure\Model;

use App\Domain\Driven\AiModelInterface;
use App\Domain\Driven\Attribute\Intent;

#[Intent(
    'memory',
    aliases: [
        'what was my last question',
        'do you remember',
        'what did i say',
        'recall last message',
        'remember what i said',
        'what did i ask',
        'can you remember',
        'what was my previous question',
        'what did we talk about',
    ],
    description: 'Recalls the user’s last messages or conversation history.'
)]
final class MemoryModel implements AiModelInterface
{
    public function handle(string $message, array $context = []): string
    {
        return $this->summarize($context);
    }

    public function handleStream(string $message, array $context = []): iterable
    {
        $summary = $this->summarize($context);
        foreach (preg_split('/\s+/', $summary, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            yield ['text' => $word];
            usleep(80_000);
        }
    }

    private function summarize(array $context): string
    {
        $history = $context['history'] ?? [];
        $name = $context['name'] ?? null;

        if (empty($history)) {
            return 'I don’t recall anything yet — we just started chatting!';
        }

        $who = $name ? "{$name}, " : '';

        // Build short recall of last 3 messages
        $recent = array_slice($history, -6);
        $lines = [];
        foreach ($recent as $entry) {
            $role = $entry['role'] === 'assistant' ? 'I said' : 'You said';
            $msg = trim($entry['message'] ?? '');
            if ($msg !== '') {
                $lines[] = "{$role}: \"{$msg}\"";
            }
        }

        $recap = implode('; ', $lines);

        return "{$who}Here's what I remember from our chat: {$recap}.";
    }
}
