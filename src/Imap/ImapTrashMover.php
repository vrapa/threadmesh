<?php

declare(strict_types=1);

namespace ThreadMesh\Imap;

use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Connection\Protocols\ImapProtocol;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Exceptions\MessageNotFoundException;

final class ImapTrashMover implements TrashMover
{
    public function __construct(private readonly TrashSafety $safety = new TrashSafety())
    {
    }

    public function sourcePresent(ImapConfiguration $configuration, array $email): bool
    {
        $client = $this->connect($configuration);
        $source = $this->source($client, $email);
        $this->validateUidValidity($source, $email);
        $metadata = $this->metadata($email);
        $uid = $this->uid($metadata);
        try {
            $message = $source->messages()->leaveUnread()->setFetchFlags(false)->getMessageByUid($uid);
        } catch (Throwable $error) {
            if ($error instanceof MessageNotFoundException) {
                return false;
            }
            throw new RuntimeException('Could not verify the source IMAP message.', 0, $error);
        }
        $this->validateIdentity($message, $metadata);
        return true;
    }

    public function move(ImapConfiguration $configuration, array $email, ?string $configuredTrashFolder): string
    {
        $client = $this->connect($configuration);
        $source = $this->source($client, $email);
        $uidValidity = $this->validateUidValidity($source, $email);
        $metadata = $this->metadata($email);
        $uid = $this->uid($metadata);
        try {
            $message = $source->messages()->leaveUnread()->setFetchFlags(false)->getMessageByUid($uid);
        } catch (Throwable $error) {
            throw new RuntimeException('Source IMAP message is unavailable; it was not moved.', 0, $error);
        }
        $this->validateIdentity($message, $metadata);

        $trash = $this->trashFolder($client, $configuredTrashFolder);
        if ($trash->path === $source->path) {
            throw new RuntimeException('The source folder is already the IMAP Trash folder.');
        }
        $protocol = $client->getConnection();
        if (!$protocol instanceof ImapProtocol) {
            throw new RuntimeException('The IMAP connection does not support safe UID MOVE.');
        }
        $capabilities = $protocol->getCapabilities()->validatedData();
        if (!is_array($capabilities) || !$this->safety->supportsMove($capabilities)) {
            throw new RuntimeException('The IMAP server does not support safe UID MOVE.');
        }
        $selected = $client->openFolder($source->path, true);
        if (($selected['uidvalidity'] ?? null) !== $uidValidity) {
            throw new RuntimeException('IMAP UIDVALIDITY changed before MOVE.');
        }
        $escaped = $protocol->escapeString($trash->path);
        if (!is_string($escaped)) {
            throw new RuntimeException('Invalid IMAP Trash folder path.');
        }
        // Deliberately avoid Message::move(): its fallback can EXPUNGE unrelated messages.
        $protocol->requestAndResponse('UID MOVE', [(string) $uid, $escaped], true)->validate();
        return $trash->path;
    }

    private function connect(ImapConfiguration $configuration): Client
    {
        $manager = new ClientManager([
            'accounts' => ['default' => [
                'host' => $configuration->host,
                'port' => $configuration->port,
                'protocol' => 'imap',
                'encryption' => $configuration->encryption === 'starttls' ? 'tls' : $configuration->encryption,
                'validate_cert' => $configuration->validateCertificate,
                'username' => $configuration->username,
                'password' => $configuration->password,
                'authentication' => null,
            ]],
            'options' => [
                'fetch' => \Webklex\PHPIMAP\IMAP::FT_PEEK,
                'sequence' => \Webklex\PHPIMAP\IMAP::ST_UID,
                'fetch_flags' => false,
                'message_key' => 'uid',
            ],
        ]);
        $client = $manager->account('default');
        $client->connect();
        return $client;
    }

    /** @param array<string, mixed> $email */
    private function source(Client $client, array $email): Folder
    {
        $metadata = $this->metadata($email);
        $folder = $metadata['folder'] ?? null;
        if (($email['connector'] ?? null) !== 'imap' || !is_string($folder) || $folder === '') {
            throw new RuntimeException('Email has no valid IMAP source folder.');
        }
        $source = $client->getFolderByPath($folder, false, true);
        if (!$source instanceof Folder || $source->no_select) {
            throw new RuntimeException('Source IMAP folder is unavailable.');
        }
        return $source;
    }

    /** @param array<string, mixed> $email */
    private function validateUidValidity(Folder $source, array $email): int
    {
        $metadata = $this->metadata($email);
        $status = $source->examine();
        $current = $status['uidvalidity'] ?? null;
        if (!is_int($current)) {
            throw new RuntimeException('IMAP did not report UIDVALIDITY.');
        }
        $this->safety->validateUidValidity($metadata, $current);
        return $current;
    }

    /** @param array<string, mixed> $metadata */
    private function validateIdentity(Message $message, array $metadata): void
    {
        $this->safety->validateRawHeader($metadata, $message->getHeader()?->raw);
    }

    /** @param array<string, mixed> $email
     *  @return array<string, mixed>
     */
    private function metadata(array $email): array
    {
        $metadata = $email['metadata'] ?? null;
        if (!is_array($metadata)) {
            return [];
        }
        $result = [];
        foreach ($metadata as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /** @param array<string, mixed> $metadata */
    private function uid(array $metadata): int
    {
        $uid = $metadata['uid'] ?? null;
        if (!is_int($uid) || $uid < 1) {
            throw new RuntimeException('Email has no valid IMAP UID.');
        }
        return $uid;
    }

    private function trashFolder(Client $client, ?string $configured): Folder
    {
        if ($configured !== null) {
            $folder = $client->getFolderByPath($configured, false, true);
            if (!$folder instanceof Folder || $folder->no_select) {
                throw new RuntimeException('Configured IMAP Trash folder is unavailable.');
            }
            return $folder;
        }

        $listing = $client->getConnection()->folders()->validatedData();
        $path = $this->safety->specialUseTrashPath(is_array($listing) ? $listing : []);
        $folder = $client->getFolderByPath($path, true, true);
        if (!$folder instanceof Folder || $folder->no_select) {
            throw new RuntimeException('IMAP Trash folder is unavailable.');
        }
        return $folder;
    }
}
