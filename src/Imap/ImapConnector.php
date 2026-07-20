<?php

declare(strict_types=1);

namespace ThreadMesh\Imap;

use Throwable;
use ThreadMesh\Contract\SourceConnector;
use ThreadMesh\Domain\ConnectionTestResult;
use ThreadMesh\Domain\SourceStream;
use ThreadMesh\Domain\SyncCursor;
use ThreadMesh\Domain\SyncRequest;
use ThreadMesh\Domain\SyncResult;
use ThreadMesh\Exception\CursorInvalidException;
use ThreadMesh\Exception\ThreadMeshException;
use ThreadMesh\Exception\TemporarySourceException;
use ThreadMesh\Imap\Client\ImapGateway;
use ThreadMesh\Imap\Cursor\ImapCursor;
use ThreadMesh\Imap\Cursor\ImapCursorCodec;
use ThreadMesh\Imap\Mapping\MessageMapper;

final class ImapConnector implements SourceConnector
{
    public function __construct(
        private readonly ImapConfiguration $configuration,
        private readonly ImapGateway $gateway,
        private readonly ImapCursorCodec $cursors = new ImapCursorCodec(),
        private readonly MessageMapper $mapper = new MessageMapper(),
    ) {
    }

    public function key(): string
    {
        return 'imap';
    }

    public function testConnection(): ConnectionTestResult
    {
        try {
            $this->gateway->connect($this->configuration);
            $this->gateway->folders();
            return ConnectionTestResult::success('IMAP connection succeeded.');
        } catch (ThreadMeshException $error) {
            return ConnectionTestResult::failure($error->getMessage());
        } catch (Throwable) {
            return ConnectionTestResult::failure('IMAP connection failed.');
        }
    }

    public function streams(): array
    {
        $this->gateway->connect($this->configuration);
        return array_map(
            static fn ($folder): SourceStream => new SourceStream($folder->id, $folder->displayName),
            $this->gateway->folders(),
        );
    }

    public function initialize(SourceStream $stream): SyncCursor
    {
        $this->gateway->connect($this->configuration);
        $status = $this->gateway->status($stream->id);
        return $this->cursors->encode(new ImapCursor($status->uidValidity, $status->highestUid));
    }

    public function synchronize(SyncRequest $request): SyncResult
    {
        $cursor = $this->cursors->decode($request->cursor);
        $this->gateway->connect($this->configuration);

        try {
            $status = $this->gateway->status($request->stream->id);
            if ($status->uidValidity !== $cursor->uidValidity) {
                throw new TemporarySourceException(sprintf(
                    'UIDVALIDITY changed for folder "%s". Reinitialize this stream before synchronizing again.',
                    $request->stream->id,
                ));
            }

            if ($status->highestUid <= $cursor->lastUid) {
                return new SyncResult([], $request->cursor, false);
            }

            $messages = $this->gateway->messagesAfter($request->stream->id, $cursor->lastUid, $request->limit + 1);
        } catch (TemporarySourceException $error) {
            if ($this->isInvalidMessageSet($error)) {
                throw new CursorInvalidException(sprintf(
                    'IMAP cursor is invalid for account "%s", folder "%s": %s Reinitialize this stream before synchronizing again.',
                    $this->configuration->accountId,
                    $request->stream->id,
                    $error->getMessage(),
                ), 0, $error);
            }

            throw new TemporarySourceException(sprintf(
                'IMAP sync failed for account "%s", folder "%s": %s',
                $this->configuration->accountId,
                $request->stream->id,
                $error->getMessage(),
            ), 0, $error);
        }

        $hasMore = count($messages) > $request->limit;
        $batch = array_slice($messages, 0, $request->limit);
        $lastUid = $cursor->lastUid;
        $items = [];
        foreach ($batch as $message) {
            if ($message->uid <= $lastUid) {
                continue;
            }
            $items[] = $this->mapper->map($this->configuration->accountId, $request->stream->id, $message);
            $lastUid = $message->uid;
        }

        return new SyncResult(
            $items,
            $this->cursors->encode(new ImapCursor($cursor->uidValidity, $lastUid)),
            $hasMore,
        );
    }

    /** @return resource */
    public function downloadAttachment(string $folderId, int $uid, string $partId)
    {
        $this->gateway->connect($this->configuration);
        return $this->gateway->downloadAttachment($folderId, $uid, $partId);
    }

    private function isInvalidMessageSet(TemporarySourceException $error): bool
    {
        return str_contains(strtolower($error->getMessage()), 'invalid message set');
    }
}
