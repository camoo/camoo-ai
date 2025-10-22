<?php

declare(strict_types=1);

namespace App\Command\AI;

use App\Infrastructure\Driven\WebSocket\AiWebSocketServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:websocket:serve', description: 'Start AI WebSocket server')]
final class WebSocketServeCommand extends Command
{
    public function __construct(private readonly AiWebSocketServer $server)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('port', InputArgument::OPTIONAL, 'Port to run the server on', 8081);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = (int)$input->getArgument('port');

        if ($port <= 0 || $port > 65535) {
            $output->writeln('<error>Invalid port. Please provide a port between 1 and 65535.</error>');

            return Command::FAILURE;
        }

        $output->writeln("<info>Starting AI WebSocket server on port {$port}...</info>");
        $this->server->run($port);

        return Command::SUCCESS;
    }
}
