<?php

declare(strict_types=1);

namespace App\Infrastructure\Model;

use App\Domain\Driven\AiModelInterface;
use App\Domain\Driven\Attribute\Intent;

/**
 * Handles greeting messages (hi, hello, greetings, etc.)
 * Returns personalized salutations based on context and message tone.
 */
#[Intent(
    'salutation',
    aliases: [
        'hi', 'hello', 'hey', 'hiya', 'howdy', 'yo', 'good morning',
        'good afternoon', 'good evening', 'greetings', 'salutations',
        'what’s up', 'hi there', 'hello there', 'hey there',
    ],
    description: 'Generates a warm, personalized greeting for the user.'
)]
final class SalutationModel implements AiModelInterface
{
    /** Handle a full non-streaming request. */
    public function handle(string $message, array $context = []): string
    {
        $name = $this->extractName($context);
        $greeting = $this->determineGreeting($message);

        return $name
            ? sprintf('%s, %s!', $greeting, $name)
            : sprintf('%s!', $greeting);
    }

    /**
     * Stream a greeting word-by-word (for WebSocket streaming).
     *
     * @return iterable<array{text: string}>
     */
    public function handleStream(string $message, array $context = []): iterable
    {
        $response = $this->handle($message, $context);
        foreach (preg_split('/\s+/', $response, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            yield ['text' => $word];
            usleep(60_000); // nice pacing for incremental updates
        }
    }

    /** Extract name from context (flat or nested form). */
    private function extractName(array $context): ?string
    {
        $name = $context['name'] ?? ($context['user']['name'] ?? null);

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    /** Infer the best greeting from message content or current time. */
    private function determineGreeting(string $message): string
    {
        $m = strtolower(trim($message));

        $patterns = [
            'good morning' => 'Good morning',
            'good afternoon' => 'Good afternoon',
            'good evening' => 'Good evening',
            'hi there' => 'Hi there',
            'hello there' => 'Hello there',
            'hey there' => 'Hey there',
            'hi' => 'Hi',
            'hey' => 'Hey',
            'hello' => 'Hello',
            'hiya' => 'Hiya',
            'howdy' => 'Howdy',
            'yo' => 'Yo',
            'greetings' => 'Greetings',
            'salutations' => 'Salutations',
        ];

        foreach ($patterns as $key => $label) {
            if (str_contains($m, $key)) {
                return $label;
            }
        }

        // fallback to time-based greeting
        $hour = (int)date('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
