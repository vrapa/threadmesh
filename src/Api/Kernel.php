<?php

declare(strict_types=1);

namespace ThreadMesh\Api;

use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ThreadMesh\Mail\ThreadMeshService;

final class Kernel
{
    public function __construct(
        private readonly ThreadMeshService $service,
        private readonly string $apiToken,
    ) {
        if ($apiToken === '') {
            throw new RuntimeException('THREADMESH_API_TOKEN is required to run the HTTP API.');
        }
    }

    public function handle(Request $request): Response
    {
        try {
            if ($request->getPathInfo() === '/health') {
                return $this->json(['status' => 'ok']);
            }
            if (!$this->authorized($request)) {
                return $this->json(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
            }
            return $this->route($request);
        } catch (Throwable $error) {
            return $this->json(['error' => $error->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    private function route(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPathInfo();

        if ($method === 'GET' && $path === '/v1/accounts') {
            return $this->json(['accounts' => $this->service->accounts()]);
        }
        if ($method === 'POST' && $path === '/v1/accounts') {
            $data = $this->body($request);
            $configuration = $data['configuration'] ?? null;
            if (!is_array($configuration)) {
                throw new RuntimeException('configuration must be a JSON object.');
            }
            $this->service->configureAccount(
                $this->string($data, 'id'),
                $this->string($data, 'displayName'),
                $this->configuration($configuration),
                $this->nullableString($data, 'secret'),
                $this->boolean($data, 'enabled', true),
            );
            return $this->json(['configured' => true], Response::HTTP_CREATED);
        }
        if ($method === 'POST' && preg_match('#^/v1/accounts/([^/]+)/test$#', $path, $matches) === 1) {
            return $this->json($this->service->testConnection(rawurldecode($matches[1])));
        }
        if ($method === 'GET' && preg_match('#^/v1/accounts/([^/]+)/folders$#', $path, $matches) === 1) {
            return $this->json(['folders' => $this->service->folders(rawurldecode($matches[1]))]);
        }
        if ($method === 'POST' && preg_match('#^/v1/accounts/([^/]+)/initialize$#', $path, $matches) === 1) {
            $data = $this->body($request);
            $streams = $data['streams'] ?? ['INBOX'];
            if (!is_array($streams) || !array_is_list($streams)) {
                throw new RuntimeException('streams must be a JSON list.');
            }
            $streamIds = [];
            foreach ($streams as $stream) {
                if (!is_string($stream) || $stream === '') {
                    throw new RuntimeException('Every stream ID must be a non-empty string.');
                }
                $streamIds[] = $stream;
            }
            return $this->json(['cursors' => $this->service->initialize(rawurldecode($matches[1]), $streamIds)]);
        }
        if ($method === 'POST' && $path === '/v1/sync') {
            $data = $this->body($request, true);
            return $this->json($this->service->sync(
                $this->nullableString($data, 'accountId'),
                $this->integer($data, 'batchSize', 100),
            ));
        }
        if ($method === 'GET' && $path === '/v1/emails') {
            return $this->json(['emails' => $this->service->unassessedEmails($this->queryInteger($request, 'limit', 20))]);
        }
        if ($method === 'GET' && preg_match('#^/v1/emails/([a-f0-9]+)$#', $path, $matches) === 1) {
            return $this->json(['email' => $this->service->email($matches[1])]);
        }
        if ($method === 'POST' && preg_match('#^/v1/emails/([a-f0-9]+)/assessment$#', $path, $matches) === 1) {
            $data = $this->body($request);
            $this->service->assess(
                $matches[1],
                $this->string($data, 'importance'),
                $this->string($data, 'category'),
                $this->string($data, 'summary'),
                $this->boolean($data, 'requiresAction'),
                $this->nullableString($data, 'dueAt'),
                $this->nullableFloat($data, 'amount'),
                $this->nullableString($data, 'currency'),
                $this->string($data, 'recommendedAction'),
                $this->string($data, 'reason'),
            );
            return $this->json(['stored' => true]);
        }
        if ($method === 'POST' && preg_match('#^/v1/emails/([a-f0-9]+)/drafts$#', $path, $matches) === 1) {
            $data = $this->body($request);
            return $this->json(['draft' => $this->service->createDraft(
                $matches[1],
                $this->string($data, 'subject'),
                $this->string($data, 'bodyText'),
            )], Response::HTTP_CREATED);
        }
        if ($method === 'GET' && $path === '/v1/alerts') {
            return $this->json(['alerts' => $this->service->alerts($this->queryInteger($request, 'limit', 50))]);
        }
        if ($method === 'POST' && preg_match('#^/v1/drafts/([a-f0-9]+)/publish$#', $path, $matches) === 1) {
            $data = $this->body($request);
            if (!$this->boolean($data, 'confirmed')) {
                throw new RuntimeException('Explicit confirmation is required before modifying IMAP.');
            }
            return $this->json(['draft' => $this->service->publishDraft($matches[1])]);
        }

        return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
    }

    private function authorized(Request $request): bool
    {
        $header = $request->headers->get('Authorization');
        return is_string($header)
            && str_starts_with($header, 'Bearer ')
            && hash_equals($this->apiToken, substr($header, 7));
    }

    /** @return array<string, mixed> */
    private function body(Request $request, bool $emptyAllowed = false): array
    {
        $content = $request->getContent();
        if ($emptyAllowed && trim($content) === '') {
            return [];
        }
        try {
            $decoded = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Request body must be valid JSON.', 0, $error);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Request body must be a JSON object.');
        }
        $result = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                throw new RuntimeException('Request JSON object contains an invalid key.');
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /** @param array<string, mixed> $data */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || trim($value) === '') {
            throw new RuntimeException(sprintf('%s must be a non-empty string.', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new RuntimeException(sprintf('%s must be a string or null.', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private function boolean(array $data, string $key, ?bool $default = null): bool
    {
        $value = $data[$key] ?? $default;
        if (!is_bool($value)) {
            throw new RuntimeException(sprintf('%s must be a boolean.', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private function integer(array $data, string $key, int $default): int
    {
        $value = $data[$key] ?? $default;
        if (!is_int($value)) {
            throw new RuntimeException(sprintf('%s must be an integer.', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $data */
    private function nullableFloat(array $data, string $key): ?float
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_int($value) && !is_float($value)) {
            throw new RuntimeException(sprintf('%s must be a number or null.', $key));
        }
        return (float) $value;
    }

    private function queryInteger(Request $request, string $key, int $default): int
    {
        $value = $request->query->get($key, (string) $default);
        if (preg_match('/^[0-9]+$/', $value) !== 1) {
            throw new RuntimeException(sprintf('%s must be a positive integer.', $key));
        }
        return (int) $value;
    }

    /**
     * @param array<array-key, mixed> $configuration
     * @return array<string, bool|float|int|string|null>
     */
    private function configuration(array $configuration): array
    {
        $result = [];
        foreach ($configuration as $key => $value) {
            if (is_int($key) || (!is_scalar($value) && $value !== null)) {
                throw new RuntimeException('configuration accepts only scalar values.');
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /** @param array<string, mixed> $data */
    private function json(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse($data, $status, ['Cache-Control' => 'no-store']);
    }
}
