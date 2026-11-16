<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Minimal MCP Streamable-HTTP client (JSON-RPC 2.0; JSON or SSE responses). */
class PlaudMcpSession
{
    private ?string $sessionId = null;
    private int $nextId = 1;
    private bool $initialized = false;

    public function __construct(private readonly string $url, private readonly string $token)
    {
    }

    /** Calls a tool and decodes its JSON payload. Returns the text when it is not JSON and $allowText is set. */
    public function callJson(string $tool, array $arguments, bool $allowText = false): mixed
    {
        $this->initialize();
        $result = $this->rpc('tools/call', ['name' => $tool, 'arguments' => (object) $arguments]);
        $text = '';
        foreach ($result['content'] ?? [] as $part) {
            if (($part['type'] ?? '') === 'text') {
                $text .= (string) ($part['text'] ?? '');
            }
        }
        if (($result['isError'] ?? false) === true) {
            throw new RuntimeException("Plaud returned an error for {$tool}.");
        }
        // Strip Plaud's "untrusted user data" wrapper, keep the JSON inside.
        if (preg_match('/<(untrusted-user-data-[a-f0-9]+)[^>]*>\s*(.*)\s*<\/\1>/s', $text, $m)) {
            $text = $m[2];
        }
        $decoded = json_decode(trim($text), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        if ($allowText) {
            return $text;
        }
        throw new RuntimeException("Unexpected response from Plaud for {$tool}.");
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->rpc('initialize', [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'PLAUD Companion', 'version' => (string) config('app.version', '1.0')],
        ]);
        $this->send(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']);
        $this->initialized = true;
    }

    /** @return array<string, mixed> */
    private function rpc(string $method, array $params): array
    {
        $id = $this->nextId++;
        $response = $this->send(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params]);
        if ($response->status() === 401) {
            throw new PlaudReconnectRequired();
        }
        if (!$response->successful()) {
            throw new RuntimeException("Plaud request failed (HTTP {$response->status()}).");
        }
        $this->sessionId ??= $response->header('Mcp-Session-Id') ?: null;

        $message = null;
        if (str_contains((string) $response->header('Content-Type'), 'text/event-stream')) {
            foreach (preg_split('/\r?\n/', $response->body()) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $candidate = json_decode(trim(substr($line, 5)), true);
                    if (is_array($candidate) && ($candidate['id'] ?? null) === $id) {
                        $message = $candidate;
                    }
                }
            }
        } else {
            $message = $response->json();
        }
        if (!is_array($message) || isset($message['error'])) {
            throw new RuntimeException("Plaud {$method} failed.");
        }

        return (array) ($message['result'] ?? []);
    }

    private function send(array $payload): Response
    {
        $request = Http::timeout(60)->withToken($this->token)->withHeaders([
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2025-06-18',
        ] + ($this->sessionId ? ['Mcp-Session-Id' => $this->sessionId] : []));

        return $request->post($this->url, $payload);
    }
}
