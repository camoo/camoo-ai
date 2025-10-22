<?php

declare(strict_types=1);

namespace App\Command\AI;

use App\Infrastructure\Driven\IntentMessageModel;
use App\Infrastructure\Model\ModelRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(name: 'app:chat', description: 'Chat with AI models based on detected intents.')]
final class ChatCommand extends Command
{
    public function __construct(
        private readonly IntentMessageModel $intentModel,
        private readonly ModelRegistry $registry
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');
        $message = $helper->ask($input, $output, new Question('Ask your question: '));

        $intentData = $this->intentModel->detectIntent($message);
        $intent = $intentData['intent'] ?? null;

        if (!$intent) {
            $output->writeln('<comment>🤔 I’m not sure what you mean.</comment>');

            return Command::SUCCESS;
        }

        $model = $this->registry->get($intent);
        if (!$model) {
            $output->writeln("<comment>⚠ No model registered for intent '{$intent}'.</comment>");

            return Command::SUCCESS;
        }

        $context = ['timezone' => 'Europe/Berlin', 'locale' => 'en_GB'];

        // 🚀 Smartly handle streaming events
        foreach ($model->handleStream($message, $context) as $event) {
            $type = $event['type'] ?? 'chunk';

            match ($type) {
                'status' => $output->writeln("<comment>{$event['text']}</comment>"),
                'chunk' => $output->writeln("<info>{$event['text']}</info>"),
                'error' => $output->writeln("<error>{$event['text']} ({$event['data']})</error>"),
                'done' => $output->writeln('<fg=green>✅ Done.</>'),
                default => $output->writeln($event['text'] ?? ''),
            };

            usleep(150_000);
        }

        return Command::SUCCESS;
    }
}
