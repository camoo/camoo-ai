<?php

declare(strict_types=1);

namespace App\Infrastructure\Model;

use App\Domain\Driven\AiModelInterface;
use App\Domain\Driven\Attribute\Intent;

#[Intent(
    'identity',
    aliases: ['who are you', 'your name', 'identify yourself', 'what is your name', 'who am I talking to'],
    description: 'Introduces the AI and responds to questions about its identity.'
)]
final class IdentityModel implements AiModelInterface
{
    private const string AI_NAME = 'EpiNett AI';

    /** Respond with a full identity message. */
    public function handle(string $message, array $context = []): string
    {
        return sprintf("I'm %s, your friendly AI assistant! How can I help you today?", self::AI_NAME);
    }

    /**
     * Stream the introduction progressively for WebSocket output.
     *
     * @return iterable<array{text: string}>
     */
    public function handleStream(string $message, array $context = []): iterable
    {
        $response = sprintf("I'm %s, your friendly AI assistant! How can I help you today?", self::AI_NAME);
        foreach (preg_split('/\s+/', $response, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            yield ['text' => $word];
            usleep(80_000); // small pacing for natural streaming
        }
    }
}
