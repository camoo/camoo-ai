<?php

declare(strict_types=1);

namespace App\Command\AI;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WebSocket\Client;

#[AsCommand(name: 'app:websocket:test', description: 'Test the AI WebSocket server using textalk/websocket')]
final class WebSocketClientCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = 'ws://localhost:8086'; // your AI WebSocket server URL
        $output->writeln("<info>🔌 Connecting to {$url} ...</info>");

        $client = new Client($url);

        // 1️⃣ Send a message
        $message = [
            'message' => 'what time is it in Berlin?',
            'context' => ['timezone' => 'Europe/Berlin'],
        ];
        $client->send(json_encode($message));

        // 2️⃣ Receive live stream
        while (true) {
            try {
                $response = $client->receive();
                $data = json_decode($response, true);
                $type = $data['type'] ?? 'chunk';
                $text = $data['text'] ?? json_encode($data);

                match ($type) {
                    'status' => $output->writeln("<comment>{$text}</comment>"),
                    'chunk' => $output->writeln("<info>{$text}</info>"),
                    'error' => $output->writeln("<error>{$text}</error>"),
                    'done' => $output->writeln('<fg=green>✅ Done.</>'),
                    default => $output->writeln($text),
                };

                if ($type === 'done' || $type === 'error') {
                    break;
                }
            } catch (\Throwable $e) {
                $output->writeln("<error>Connection error: {$e->getMessage()}</error>");
                break;
            }
        }

        $client->close();
        $output->writeln('<comment>🔒 Connection closed.</comment>');

        return Command::SUCCESS;
    }
}
