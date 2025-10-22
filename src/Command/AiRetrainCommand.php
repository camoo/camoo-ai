<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Driven\IntentMessageModel;
use League\Csv\Reader;
use League\Csv\Statement;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'ai:retrain',
    description: 'Retrains the AI intent dataset using unclassified user messages (AI-assisted).'
)]
final class AiRetrainCommand extends Command
{
    private string $baseDir;

    private IntentMessageModel $intentModel;

    public function __construct()
    {
        parent::__construct();
        $this->baseDir = dirname(__DIR__, 2) . '/datasets';
    }

    protected function configure(): void
    {
        $this
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Locale to retrain', 'en_GB')
            ->addOption('auto', 'a', InputOption::VALUE_NONE, 'Automatically assign intents without prompt')
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED, 'Similarity threshold for auto mode', 0.6);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = $input->getOption('locale');
        $auto = (bool)$input->getOption('auto');
        $threshold = (float)$input->getOption('threshold');

        $unclassifiedPath = "{$this->baseDir}/unclassified/{$locale}.csv";
        $localeDir = "{$this->baseDir}/locale/{$locale}/";

        if (!file_exists($unclassifiedPath)) {
            $io->info("No unclassified data found for {$locale}.");

            return Command::SUCCESS;
        }

        $rows = $this->readCsv($unclassifiedPath);
        if (empty($rows)) {
            $io->info('No new messages to process.');

            return Command::SUCCESS;
        }

        $io->section('Processing ' . count($rows) . ' unclassified messages...');
        $intents = $this->loadIntentFiles($localeDir);

        // initialize intent model for similarity computation
        $this->intentModel = new IntentMessageModel($locale);

        $helper = $this->getHelper('question');

        foreach ($rows as $entry) {
            $message = trim($entry['message']);
            if ($message === '') {
                continue;
            }

            $io->writeln("\n🗣️  <info>{$message}</info>");

            $intent = null;

            if ($auto) {
                [$intent, $score] = $this->predictIntent($message, $threshold);
                if ($intent === null) {
                    $io->writeln("<comment>Skipped (low similarity: {$score}).</comment>");
                    continue;
                }
                $io->writeln("<comment>Auto-assigned intent '{$intent}' (score: {$score})</comment>");
            } else {
                $question = new ChoiceQuestion(
                    'Assign intent (or press Enter to skip)',
                    array_keys($intents),
                    null
                );
                $intent = $helper->ask($input, $output, $question);
            }

            if (!$intent) {
                $io->writeln('<comment>Skipped.</comment>');
                continue;
            }

            $intents[$intent]['examples'][] = $message;
            $io->writeln("<info>✅ Added to intent '{$intent}'</info>");
        }

        $this->saveIntentFiles($intents, $localeDir);
        unlink($unclassifiedPath);

        $io->success('Retraining complete. Intents updated and unclassified data cleared.');

        return Command::SUCCESS;
    }

    private function readCsv(string $path): array
    {
        $csv = Reader::from($path);
        $csv->setHeaderOffset(0);

        $stmt = (new Statement())->offset(0);
        $records = $stmt->process($csv);

        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'message' => $record['message'] ?? $record[0] ?? '',
                'score' => (float)($record['score'] ?? $record[1] ?? 0),
                'timestamp' => $record['timestamp'] ?? $record[2] ?? '',
            ];
        }

        return $rows;
    }

    private function loadIntentFiles(string $localeDir): array
    {
        $files = glob($localeDir . 'intent-*.json');
        if (!$files) {
            throw new RuntimeException("No intent files found in {$localeDir}");
        }

        $intents = [];
        foreach ($files as $file) {
            $intentData = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $intents[$intentData['intent']] = $intentData;
        }

        return $intents;
    }

    private function saveIntentFiles(array $intents, string $localeDir): void
    {
        foreach ($intents as $intent => $data) {
            $path = "{$localeDir}intent-{$intent}.json";
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /** Predicts the best intent using the actual IntentMessageModel similarity scoring. */
    private function predictIntent(string $message, float $threshold): array
    {
        $result = $this->intentModel->detectIntent($message);

        if (!$result || !$result['intent']) {
            return [null, $result['score'] ?? 0.0];
        }

        $intent = $result['intent'];
        $score = round($result['score'], 3);

        if ($score < $threshold) {
            return [null, $score];
        }

        return [$intent, $score];
    }
}
