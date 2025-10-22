<?php

declare(strict_types=1);

namespace App\Infrastructure\Model;

use App\Domain\Driven\AiModelInterface;
use App\Domain\Driven\Attribute\Intent;

#[Intent(
    'joke',
    aliases: ['tell me a joke', 'make me laugh', 'funny', 'humor', 'another one'],
    description: 'Tells a short, clean joke to lighten the mood.'
)]
final class JokeModel implements AiModelInterface
{
    /** @var string[] */
    private array $jokes = [
        'Why do Java developers wear glasses? Because they don’t C#!',
        'I told my computer I needed a break... and it froze.',
        'Why did the web developer leave the restaurant? Because of the bad table layout.',
        'There are 10 types of people in the world: those who understand binary and those who don’t.',
        'Debugging: Being the detective in a crime movie where you are also the murderer.',
        'Why did the AI cross the road? To optimize the chicken’s path to the other side.',
        'I just got fired from the keyboard factory. They said I wasn’t putting in enough shifts.',
        'The cloud is just someone else’s computer ☁️',
        'Never trust an atom — they make up everything!',
        'I would tell you a UDP joke, but you might not get it.',
    ];

    public function handle(string $message, array $context = []): string
    {
        return $this->randomJoke();
    }

    public function handleStream(string $message, array $context = []): iterable
    {
        $joke = $this->randomJoke();

        foreach (preg_split('/\s+/', $joke, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            yield ['text' => $word];
            usleep(80_000);
        }
    }

    private function randomJoke(): string
    {
        return $this->jokes[array_rand($this->jokes)];
    }
}
