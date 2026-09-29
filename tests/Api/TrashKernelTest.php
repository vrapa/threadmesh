<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Api;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use ThreadMesh\Api\Kernel;
use ThreadMesh\Bootstrap;
use ThreadMesh\Domain\Actor;
use ThreadMesh\Domain\Item;
use ThreadMesh\Domain\ItemContent;
use ThreadMesh\Domain\ItemId;
use ThreadMesh\Domain\ItemStatus;
use ThreadMesh\Domain\ItemType;
use ThreadMesh\Domain\SourceReference;
use ThreadMesh\Imap\ImapConfiguration;
use ThreadMesh\Imap\TrashMover;
use ThreadMesh\Storage\SecretCipher;
use ThreadMesh\Storage\SqliteConnection;
use ThreadMesh\Storage\SqliteStore;

final class TrashKernelTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/threadmesh-trash-api-' . bin2hex(random_bytes(8)) . '.sqlite';
        putenv('THREADMESH_MASTER_KEY=' . base64_encode(random_bytes(32)));
    }

    protected function tearDown(): void
    {
        putenv('THREADMESH_MASTER_KEY');
        foreach ([$this->path, $this->path . '-wal', $this->path . '-shm'] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testAuthenticatedTrashRouteMovesAndHidesEmail(): void
    {
        $store = new SqliteStore(new SqliteConnection($this->path), SecretCipher::fromEnvironment());
        $store->configureAccount('work', 'Work', [
            'host' => 'imap.example.test', 'username' => 'me@example.test', 'trashFolder' => 'Trash',
        ], 'test-password');
        $store->upsert(new Item(
            new ItemId('aabb'), new SourceReference('imap', 'work', 'INBOX:1'), ItemType::Email,
            'Test', new ItemContent('Body', null), new Actor('sender', 'Sender'),
            ItemStatus::New, new DateTimeImmutable('2026-09-29T08:00:00+00:00'),
            metadata: ['folder' => 'INBOX', 'uid' => 1, 'uidValidity' => 42],
        ));
        $mover = new class implements TrashMover {
            public int $moves = 0;
            public function sourcePresent(ImapConfiguration $configuration, array $email): bool { return true; }
            public function move(ImapConfiguration $configuration, array $email, ?string $configuredTrashFolder): string
            {
                ++$this->moves;
                return 'Trash';
            }
        };
        $kernel = new Kernel(Bootstrap::create($this->path, $mover), 'test-token');
        self::assertSame(401, $kernel->handle(Request::create('/v1/emails/aabb/trash', 'POST'))->getStatusCode());
        self::assertSame(404, $kernel->handle($this->authorized('/v1/emails/aabb/trash', 'GET'))->getStatusCode());
        $response = $kernel->handle($this->authorized('/v1/emails/aabb/trash', 'POST'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"trashed":true}', $response->getContent());
        self::assertSame(200, $kernel->handle($this->authorized('/v1/emails/aabb/trash', 'POST'))->getStatusCode());
        self::assertSame(1, $mover->moves);
        self::assertSame([], $store->mailboxEmails(new DateTimeImmutable('2026-01-01')));
    }

    private function authorized(string $path, string $method): Request
    {
        $request = Request::create($path, $method);
        $request->headers->set('Authorization', 'Bearer test-token');
        return $request;
    }
}
