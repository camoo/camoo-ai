<?php

declare(strict_types=1);

namespace App\Infrastructure\Driven;

use App\Infrastructure\Exception\InvalidDatasetException;
use App\Infrastructure\Model\ModelRegistry;
use Psr\Log\LogLevel;

final class IntentMessageModel
{
    private array $examples = [];

    private string $locale;

    public function __construct(private readonly ModelRegistry $registry, string $locale = 'en_GB')
    {
        $this->locale = $locale;
        $this->loadDataset();
    }

    private function normalizeScores(array $scores): array
    {
        $sum = array_sum($scores);
        if ($sum <= 0) {
            return [];
        }

        $normalized = [];
        foreach ($scores as $intent => $score) {
            $normalized[] = [
                'intent' => $intent,
                'score' => round($score / $sum, 3),
            ];
        }

        return $normalized;
    }

    private function loadDataset(): void
    {
        $dir = __DIR__ . "/../../../datasets/locale/{$this->locale}/";
        $files = glob($dir . 'intent-*.json');
        if (empty($files)) {
            $dir = __DIR__ . '/../../../datasets/locale/en_GB/';
            $files = glob($dir . 'intent-*.json');
        }

        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $intentName = basename($file, '.json');
            $intentName = str_replace('intent-', '', $intentName);

            if (isset($content['examples'])) {
                $this->examples[$content['intent'] ?? $intentName] = $content;
            } elseif (is_array($content)) {
                $this->examples[$intentName] = [
                    'intent' => $intentName,
                    'examples' => $content,
                ];
            } else {
                throw new InvalidDatasetException("Invalid dataset format in {$file}");
            }
        }

        // 🧠 Enrich examples with model aliases
        foreach ($this->registry->getAliases() as $intent => $aliases) {
            $this->examples[$intent]['examples'] = array_unique(array_merge(
                $this->examples[$intent]['examples'] ?? [],
                $aliases
            ));
        }
    }

    public function detectIntent(string $message): ?array
    {
        $message = strtolower(trim($message));
        $scores = [];

        foreach ($this->examples as $intent => $data) {
            foreach ($data['examples'] as $example) {
                $similarity = $this->cosineSimilarity($message, strtolower($example));
                if ($similarity > 0.2) {
                    $scores[$intent] = max($scores[$intent] ?? 0, $similarity);
                }
            }
        }

        if (empty($scores)) {
            //  Log unclassified message
            new UnclassifiedLogger()->log(LogLevel::NOTICE, $message, [
                'locale' => $this->locale,
                'score' => 0.0,
            ]);
            return null;
        }

        arsort($scores);
        $bestIntent = array_key_first($scores);
        $bestScore = reset($scores);
        $normalized = $this->normalizeScores($scores);

        if ($bestScore < 0.4) {
            // Log uncertain message
            new UnclassifiedLogger()->log(LogLevel::NOTICE, $message, [
                'locale' => $this->locale,
                'score' => $bestScore,
            ]);
            return [
                'intent' => null,
                'score' => $bestScore,
                'alternatives' => $normalized,
                'class' => null,
            ];
        }

        $model = $this->registry->get($bestIntent);

        return [
            'intent' => $bestIntent,
            'score' => $bestScore,
            'alternatives' => $normalized,
            'class' => $model ? get_class($model) : null,
        ];
    }

    private function cosineSimilarity(string $a, string $b): float
    {
        $v1 = $this->vectorize($a);
        $v2 = $this->vectorize($b);

        $dot = 0;
        $normA = 0;
        $normB = 0;
        foreach ($v1 as $word => $count) {
            $dot += $count * ($v2[$word] ?? 0);
            $normA += $count ** 2;
        }
        foreach ($v2 as $count) {
            $normB += $count ** 2;
        }

        return $dot / (sqrt($normA) * sqrt($normB) + 1e-8);
    }

    private function vectorize(string $text): array
    {
        $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', '', $text));
        $vec = [];
        foreach ($words as $w) {
            if ($w !== '') {
                $vec[$w] = ($vec[$w] ?? 0) + 1;
            }
        }

        return $vec;
    }
}
