<?php

declare(strict_types=1);

namespace App\Infrastructure\Driven\WebSocket;

use App\Infrastructure\Driven\IntentMessageModel;
use App\Infrastructure\Model\ModelRegistry;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Message;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Loop;
use React\Socket\ConnectionInterface;
use React\Socket\SocketServer;

final class AiWebSocketServer
{
    // point to session storage directory, symfony project structure assumed
    private const string SESSION_DIR = __DIR__ . '/../../../../var/sessions/ai/';

    /**
     * Each connected client keeps its own state.
     *
     * @var array<int, array{
     *     conn: ConnectionInterface,
     *     buffer: string,
     *     handshake?: bool,
     *     context: array<string,mixed>
     * }>
     */
    private array $clients = [];

    public function __construct(
        private readonly IntentMessageModel $intentModel,
        private readonly ModelRegistry $registry
    ) {
    }

    public function run(int $port = 8081): void
    {
        $loop = Loop::get();
        $server = new SocketServer("0.0.0.0:{$port}", [], $loop);

        echo "🚀 AI WebSocket server running on ws://localhost:{$port}\n";

        $server->on('connection', function (ConnectionInterface $conn): void {
            $id = spl_object_id($conn);
            $sessionId = bin2hex(random_bytes(8)); // simple unique session id
            $context = $this->loadContext($sessionId);

            // 🧠 Initialize per-client memory
            $this->clients[$id] = [
                'conn' => $conn,
                'buffer' => '',
                'sessionId' => $sessionId,
                'context' => $context,
            ];

            $conn->on('data', function (string $data) use ($id): void {
                $client = &$this->clients[$id];
                $client['buffer'] .= $data;

                // Perform handshake when full HTTP headers are available
                if (!isset($client['handshake']) && str_contains($client['buffer'], "\r\n\r\n")) {
                    $this->handleHandshake($id, $client);
                }
            });

            $conn->on('error', function (\Throwable $e) use ($id): void {
                echo "❌ Error with client #{$id}: {$e->getMessage()}\n";
                $this->disconnect($id);
            });

            $conn->on('close', fn () => $this->disconnect($id));
        });

        $loop->run();
    }

    /**
     * Handle the HTTP -> WebSocket handshake and switch connection to frame mode.
     *
     * @param array{conn: ConnectionInterface, buffer: string, handshake?: bool, context: array<string,mixed>} $client
     */
    private function handleHandshake(int $id, array &$client): void
    {
        $conn = $client['conn'];

        try {
            $handshake = new ServerNegotiator(new RequestVerifier(), new HttpFactory());
            $request = Message::parseRequest($client['buffer']);
            $response = $handshake->handshake($request);

            if ($response->getStatusCode() !== 101) {
                echo "❌ Handshake rejected ({$response->getStatusCode()} {$response->getReasonPhrase()})\n";
                $conn->end("HTTP/1.1 {$response->getStatusCode()} {$response->getReasonPhrase()}\r\n\r\n");
                $this->disconnect($id);

                return;
            }

            // send raw HTTP response for the upgrade
            $rawResponse = sprintf(
                "HTTP/%s %d %s\r\n",
                $response->getProtocolVersion(),
                $response->getStatusCode(),
                $response->getReasonPhrase()
            );
            foreach ($response->getHeaders() as $name => $values) {
                $rawResponse .= $name . ': ' . implode(', ', $values) . "\r\n";
            }
            $rawResponse .= "\r\n";

            $conn->write($rawResponse);
            $client['handshake'] = true;
            echo "🤝 Handshake complete with client #{$id}\n";

            // Switch to WebSocket frame handling
            $msgBuffer = new MessageBuffer(
                new CloseFrameChecker(),
                fn (MessageInterface $msg) => $this->onMessage($id, $msg->getPayload()),
                fn (Frame $frame) => $conn->write((string)$frame)
            );

            $conn->removeAllListeners('data');
            $conn->on('data', [$msgBuffer, 'onData']);

            Loop::futureTick(fn () => $this->send($conn, [
                'type' => 'welcome',
                'text' => 'Connected!',
            ]));
        } catch (\Throwable $e) {
            echo "❌ Handshake failed for client #{$id}: {$e->getMessage()}\n";
            $conn->end("HTTP/1.1 400 Bad Request\r\n\r\n");
            $this->disconnect($id);
        } finally {
            $client['buffer'] = '';
        }
    }

    /** Send a JSON message. */
    private function send(ConnectionInterface $conn, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        try {
            $frame = $this->createRatchetFrame($json);
            if ($frame instanceof Frame) {
                $conn->write((string)$frame);

                return;
            }
        } catch (\Throwable) {
            // ignore and fallback to manual framing
        }

        // Fallback: write a manually built frame string (server frames are not masked)
        $conn->write($this->buildFrame($json));
    }

    /** Try to instantiate a Ratchet Frame with explicit RSV=0 when supported. */
    private function createRatchetFrame(string $payload): ?Frame
    {

        try {
            $ref = new \ReflectionClass(Frame::class);
            $ctor = $ref->getConstructor();
            $paramCount = $ctor ? $ctor->getNumberOfParameters() : 0;

            if ($paramCount >= 4) {
                return new Frame($payload, true, Frame::OP_TEXT, 0);
            }
            if ($paramCount === 3) {
                return new Frame($payload, true, Frame::OP_TEXT);
            }

            $frame = new Frame($payload);
        } catch (\Throwable) {
         // ignore
        }
        return $frame ?? null;
    }

    /** Build a raw WebSocket text frame string (server-to-client, not masked). */
    private function buildFrame(string $payload): string
    {
        $len = strlen($payload);
        $firstByte = 0x80 | 0x01; // FIN=1, RSV=0, OPCODE=1 (text)
        $frameHead = chr($firstByte);

        if ($len <= 125) {
            $frameHead .= chr($len);
        } elseif ($len <= 0xFFFF) {
            $frameHead .= chr(126) . pack('n', $len);
        } else {
            $hi = ($len & 0xFFFFFFFF00000000) >> 32;
            $lo = $len & 0xFFFFFFFF;
            $frameHead .= chr(127) . pack('N', (int)$hi) . pack('N', (int)$lo);
        }

        return $frameHead . $payload;
    }

    private function onMessage(int $id, string $payload): void
    {
        if (!isset($this->clients[$id])) {
            echo "⚠️ Message for unknown client #{$id}\n";

            return;
        }

        $client = &$this->clients[$id];
        $conn = $client['conn'];

        echo "Received payload from #{$id}: {$payload}\n";

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $message = $data['message'] ?? '';
            $contextRaw = $data['context'] ?? [];

            // Merge incoming context into persistent memory
            if (is_array($contextRaw)) {
                $client['context'] = array_merge($client['context'], $contextRaw);
            }

            $context = $client['context'];

            $intentData = $this->intentModel->detectIntent($message);
            $intent = $intentData['intent'] ?? null;

            // Append to conversation history
            $context['last_message'] = $message;
            $context['last_intent'] = $intent;

            $context['history'][] = [
                'timestamp' => date('c'),
                'role' => 'user',
                'message' => $message,
                'intent' => $intent,
            ];

            // keep max 5 messages per direction
            $context['history'] = $this->trimHistory($context['history'] ?? [], 10);

            $client['context'] = $context;

            if (!$intent) {
                $this->send($conn, ['type' => 'error', 'text' => 'No intent detected']);

                return;
            }

            $model = $this->registry->get($intent);
            if (!$model) {
                $this->send($conn, ['type' => 'error', 'text' => "No model for {$intent}"]);

                return;
            }

            foreach ($model->handleStream($message, $context) as $event) {
                $text = $event['text'] ?? json_encode($event);
                $this->send($conn, ['type' => 'chunk', 'text' => $text]);

                // Store assistant output incrementally
                $context['history'][] = [
                    'timestamp' => date('c'),
                    'role' => 'assistant',
                    'message' => $text,
                ];
                $context['history'] = $this->trimHistory($context['history'], 10);
                usleep(100_000);
            }

            $this->send($conn, ['type' => 'done']);
            $client['context'] = $context;
            $this->saveContext($client['sessionId'], $client['context']);
        } catch (\Throwable $e) {
            $this->send($conn, ['type' => 'error', 'text' => $e->getMessage()]);
        }
    }

    /**
     * Keep only the latest $limit history entries.
     *
     * @param array<int,array<string,mixed>> $history
     *
     * @return array<int,array<string,mixed>>
     */
    private function trimHistory(array $history, int $limit = 10): array
    {
        $count = count($history);
        if ($count > $limit) {
            $history = array_slice($history, $count - $limit);
        }

        return array_values($history);
    }

    /**
     * Load context from file storage.
     *
     * @return array<string,mixed>
     */
    private function loadContext(string $sessionId): array
    {
        $file = self::SESSION_DIR . "{$sessionId}.json";
        if (!is_dir(self::SESSION_DIR)) {
            mkdir(self::SESSION_DIR, 0777, true);
        }
        if (is_file($file)) {
            try {
                return json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR) ?: [];
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }

    /** Save context to file storage. */
    private function saveContext(string $sessionId, array $context): void
    {
        $file = self::SESSION_DIR . "{$sessionId}.json";
        if (!is_dir(self::SESSION_DIR)) {
            mkdir(self::SESSION_DIR, 0777, true);
        }
        try {
            file_put_contents($file, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            echo "⚠️ Failed to save session {$sessionId}: {$e->getMessage()}\n";
        }
    }

    private function disconnect(int $id): void
    {
        if (!isset($this->clients[$id])) {
            return;
        }

        $sessionId = $this->clients[$id]['sessionId'] ?? null;
        $context = $this->clients[$id]['context'] ?? [];

        if ($sessionId) {
            $this->saveContext($sessionId, $context);
            echo "💾 Session {$sessionId} saved.\n";
        }

        unset($this->clients[$id]);
        echo "👋 Client #{$id} disconnected\n";
    }
}
