<?php

declare(strict_types=1);

namespace App\Infrastructure\Driven;

use League\Csv\Bom;
use League\Csv\Exception;
use League\Csv\Writer;
use Psr\Log\AbstractLogger;

final class UnclassifiedLogger extends AbstractLogger
{
    private string $baseDir;

    public function __construct(?string $baseDir = null)
    {
        $this->baseDir = $baseDir ?? dirname(__DIR__, 3) . '/datasets/unclassified';
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    public function log($level, $message, array $context = []): void
    {
        $locale = $context['locale'] ?? 'en_GB';
        $score = $context['score'] ?? 0.0;
        $this->applyLog((string)$message, $locale, (float)$score);
    }

    private function applyLog(string $message, string $locale = 'en_GB', float $score = 0.0): void
    {
        $message = trim($message);
        if ($message === '') {
            return; // avoid empty lines
        }

        $path = "{$this->baseDir}/{$locale}.csv";
        $isNew = !file_exists($path);

        try {
            $csv = Writer::from($path, 'a+');
            $csv->setDelimiter(',');
            $csv->setOutputBOM(Bom::Utf8);

            // Write header if file is new or empty
            if ($isNew || filesize($path) === 0) {
                $csv->insertOne(['message', 'score', 'timestamp']);
            }

            $csv->insertOne([
                $message,
                round($score, 4),
                date('c'),
            ]);
        } catch (Exception $e) {
            error_log('⚠️ Failed to log unclassified message: ' . $e->getMessage());
        }
    }
}
