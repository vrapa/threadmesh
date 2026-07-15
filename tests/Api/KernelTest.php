<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Api;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use ThreadMesh\Api\Kernel;
use ThreadMesh\Bootstrap;

final class KernelTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/threadmesh-api-' . bin2hex(random_bytes(8)) . '.sqlite';
        putenv('THREADMESH_MASTER_KEY=' . base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void
    {
        putenv('THREADMESH_MASTER_KEY');
        foreach ([$this->path, $this->path . '-shm', $this->path . '-wal'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testHealthIsPublicAndAccountsRequireBearerToken(): void
    {
        $kernel = new Kernel(Bootstrap::create($this->path), 'test-token');
        self::assertSame(200, $kernel->handle(Request::create('/health'))->getStatusCode());
        self::assertSame(401, $kernel->handle(Request::create('/v1/accounts'))->getStatusCode());

        $request = Request::create('/v1/accounts');
        $request->headers->set('Authorization', 'Bearer test-token');
        $response = $kernel->handle($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"accounts":[]', (string) $response->getContent());
    }
}
