<?php

declare(strict_types=1);

namespace ThreadMesh\Dashboard\Model;

use JsonException;
use RuntimeException;

final class ThreadMeshApi
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
    ) {
        if ($this->baseUrl === '' || $this->token === '') {
            throw new RuntimeException('Dashboard API connection is not configured.');
        }
    }

    /**
     * @param array<string, bool|int|string> $filters
     * @return list<array<string, mixed>>
     */
    public function mailbox(array $filters): array
    {
        $response = $this->get('/v1/mailbox?' . http_build_query($filters, '', '&', PHP_QUERY_RFC3986));
        $emails = $response['emails'] ?? null;
        if (!is_array($emails) || !array_is_list($emails)) {
            throw new RuntimeException('ThreadMesh returned an invalid mailbox response.');
        }

        return array_values(array_filter($emails, 'is_array'));
    }

    /** @return array<string, mixed> */
    public function email(string $id): array
    {
        if (preg_match('/^[a-f0-9]+$/D', $id) !== 1) {
            throw new RuntimeException('Invalid email ID.');
        }
        $response = $this->get('/v1/emails/' . $id);
        $email = $response['email'] ?? null;
        if (!is_array($email)) {
            throw new RuntimeException('ThreadMesh returned an invalid email response.');
        }

        return $email;
    }

    public function trashEmail(string $id): string
    {
        if (preg_match('/^[a-f0-9]+$/D', $id) !== 1) {
            throw new RuntimeException('Invalid email ID.');
        }
        $response = $this->request('POST', '/v1/emails/' . $id . '/trash');
        if (($response['removedLocal'] ?? null) === true) {
            return 'removed_local';
        }
        if (($response['trashed'] ?? null) === true) {
            return 'moved';
        }
        throw new RuntimeException('ThreadMesh did not confirm the message removal.');
    }

    /** @return array<string, mixed> */
    private function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** @return array<string, mixed> */
    private function request(string $method, string $path): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => "Accept: application/json\r\nContent-Type: application/json\r\nAuthorization: Bearer {$this->token}\r\n",
                'content' => $method === 'POST' ? '{}' : '',
                'ignore_errors' => true,
                'timeout' => 10,
            ],
        ]);
        $body = @file_get_contents(rtrim($this->baseUrl, '/') . $path, false, $context);
        $status = $this->statusCode($http_response_header ?? []);
        if (!is_string($body) || $status < 200 || $status >= 300) {
            if ($status === 409 && is_string($body)) {
                $failure = json_decode($body, true);
                if (is_array($failure) && ($failure['code'] ?? null) === 'source_absent') {
                    throw new RuntimeException('Zpráva už není v původní složce IMAP. Zkontrolujte koš nebo jiné složky.');
                }
            }
            throw new RuntimeException(sprintf('ThreadMesh API request failed (HTTP %d).', $status));
        }
        try {
            $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('ThreadMesh returned invalid JSON.', 0, $error);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('ThreadMesh returned an invalid response.');
        }

        return $decoded;
    }

    /** @param list<string> $headers */
    private function statusCode(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
