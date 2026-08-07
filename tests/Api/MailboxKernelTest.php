<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Api;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use ThreadMesh\Api\Kernel;
use ThreadMesh\Bootstrap;

final class MailboxKernelTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/threadmesh-mailbox-api-' . bin2hex(random_bytes(8)) . '.sqlite';
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

    public function testMailboxDefaultsToSevenDaysAndRequiresAuthentication(): void
    {
        $kernel = new Kernel(Bootstrap::create($this->path), 'test-token');
        self::assertSame(401, $kernel->handle(Request::create('/v1/mailbox'))->getStatusCode());

        $request = Request::create('/v1/mailbox?importance=high,critical&assessed=true&requiresAction=1');
        $request->headers->set('Authorization', 'Bearer test-token');
        $response = $kernel->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('"emails":[]', (string) $response->getContent());
    }

    public function testMailboxRejectsInvalidFilters(): void
    {
        $kernel = new Kernel(Bootstrap::create($this->path), 'test-token');
        $request = Request::create('/v1/mailbox?since=not-a-date');
        $request->headers->set('Authorization', 'Bearer test-token');

        self::assertSame(400, $kernel->handle($request)->getStatusCode());
    }
}
