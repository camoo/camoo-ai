<?php

declare(strict_types=1);

namespace App\Infrastructure\Model;

use App\Domain\Driven\AiModelInterface;
use App\Domain\Driven\Attribute\Intent;

#[Intent('weather', aliases: ['temperature', 'forecast'])]
final class WeatherModel implements AiModelInterface
{
    public function handle(string $message, array $context = []): string
    {
        $city = $context['city'] ?? 'Berlin';

        return sprintf('🌤️ The weather in %s looks great today!', $city);
    }

    public function handleStream(string $message, array $context = []): iterable
    {
        // TODO: Implement handleStream() method.
    }
}
