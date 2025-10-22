<?php

declare(strict_types=1);

namespace App\Controller;

use App\Infrastructure\Driven\IntentMessageModel;
use App\Infrastructure\Model\ModelRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ChatController extends AbstractController
{
    private const SESSION_DIR = __DIR__ . '/../../var/sessions/ai';

    public function __construct(
        private readonly IntentMessageModel $intentModel,
        private readonly ModelRegistry $registry,
    ) {
    }

    #[Route('/ai/chat', name: 'ai_chat', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): StreamedResponse
    {
        $message = trim((string)$request->get('message', ''));
        $sessionId = $this->sanitizeSessionId((string)$request->get('sessionId', '')) ?: bin2hex(random_bytes(8));
        $locale = $request->getLocale();
        $context = $this->loadContext($sessionId);

        $context['timezone'] ??= 'Europe/Berlin';
        $context['locale'] = $locale;

        $intentData = $this->intentModel->detectIntent($message);
        $intent = $intentData['intent'] ?? null;

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
        ];

        return new StreamedResponse(function () use ($message, $intent, $context, $sessionId): void {
            $this->safeFlush(str_repeat(' ', 2048) . "\n");

            if ($message === '') {
                $this->sendEvent(['type' => 'error', 'text' => 'Empty message']);

                return;
            }

            if (!$intent) {
                $this->sendEvent(['type' => 'error', 'text' => 'No intent detected']);

                return;
            }

            $model = $this->registry->get($intent);
            if (!$model) {
                $this->sendEvent(['type' => 'error', 'text' => "No model registered for intent '{$intent}'"]);

                return;
            }

            // memory tracking
            $context['last_message'] = $message;
            $context['last_intent'] = $intent;
            $context['history'][] = [
                'timestamp' => date('c'),
                'role' => 'user',
                'message' => $message,
                'intent' => $intent,
            ];
            $context['history'] = $this->trimHistory($context['history'] ?? [], 10);

            foreach ($model->handleStream($message, $context) as $event) {
                $payload = ['type' => 'chunk', 'text' => $event['text'] ?? json_encode($event)];
                $this->sendEvent($payload);

                $context['history'][] = [
                    'timestamp' => date('c'),
                    'role' => 'assistant',
                    'message' => $payload['text'],
                ];
                $context['history'] = $this->trimHistory($context['history'], 10);

                usleep(150_000);
            }

            $this->sendEvent(['type' => 'done', 'sessionId' => $sessionId]);
            $this->saveContext($sessionId, $context);
        }, 200, $headers);
    }

    /** Safely send one SSE event. */
    private function sendEvent(array $data): void
    {
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
        $this->safeFlush();
    }

    /** Flush only if possible (prevents ob_flush notice). */
    private function safeFlush(string $extra = ''): void
    {
        if ($extra !== '') {
            echo $extra;
        }

        if (function_exists('ob_get_level') && ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }

    private function loadContext(string $sessionId): array
    {
        $dir = $this->ensureSessionDir();
        $file = $dir . $sessionId . '.json';

        if (is_file($file) && is_readable($file)) {
            try {
                $contents = (string)file_get_contents($file);

                return json_decode($contents, true, 512, JSON_THROW_ON_ERROR) ?: [];
            } catch (\Throwable) {
                return [];
            }
        }

        return [];
    }

    private function saveContext(string $sessionId, array $context): void
    {
        $dir = $this->ensureSessionDir();
        $file = $dir . $sessionId . '.json';

        try {
            $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            file_put_contents($file, $json, LOCK_EX);
        } catch (\Throwable $e) {
            error_log("⚠️ Failed to save session {$sessionId}: {$e->getMessage()}");
        }
    }

    private function trimHistory(array $history, int $limit = 10): array
    {
        $count = count($history);
        if ($count <= $limit) {
            return array_values($history);
        }

        return array_values(array_slice($history, $count - $limit));
    }

    /** Ensure the session directory exists and return it with trailing DIRECTORY_SEPARATOR. */
    private function ensureSessionDir(): string
    {
        $dir = rtrim(self::SESSION_DIR, '/\\') . DIRECTORY_SEPARATOR;

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    /** Basic sanitation for externally provided session ids. */
    private function sanitizeSessionId(string $sessionId): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-]/', '', $sessionId);

        // limit length to avoid long filenames
        return substr($clean ?? '', 0, 64);
    }
}
