<?php

declare(strict_types=1);

namespace ThreadMesh\Imap\Client;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;
use ThreadMesh\Exception\AuthenticationException;
use ThreadMesh\Exception\ConnectionException;
use ThreadMesh\Exception\TemporarySourceException;
use ThreadMesh\Exception\ThreadMeshException;
use ThreadMesh\Imap\ImapConfiguration;
use ThreadMesh\Imap\MimeHeaderDecoder;
use Webklex\PHPIMAP\Attachment as WebklexAttachment;
use Webklex\PHPIMAP\Attribute;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\WhereQuery;

final class WebklexImapGateway implements ImapGateway
{
    private ?Client $client = null;
    private readonly MimeHeaderDecoder $mimeHeaders;

    public function __construct(?MimeHeaderDecoder $mimeHeaders = null)
    {
        $this->mimeHeaders = $mimeHeaders ?? new MimeHeaderDecoder();
    }

    public function connect(ImapConfiguration $configuration): void
    {
        if ($this->client !== null) {
            return;
        }

        try {
            $manager = new ClientManager([
                'accounts' => [
                    'default' => [
                        'host' => $configuration->host,
                        'port' => $configuration->port,
                        'protocol' => 'imap',
                        'encryption' => $configuration->encryption === 'starttls' ? 'tls' : $configuration->encryption,
                        'validate_cert' => $configuration->validateCertificate,
                        'username' => $configuration->username,
                        'password' => $configuration->password,
                        'authentication' => null,
                    ],
                ],
                'options' => [
                    'fetch' => \Webklex\PHPIMAP\IMAP::FT_PEEK,
                    'sequence' => \Webklex\PHPIMAP\IMAP::ST_UID,
                    'fetch_body' => true,
                    'fetch_flags' => false,
                    'message_key' => 'uid',
                    'fetch_order' => 'asc',
                ],
            ]);
            $this->client = $manager->account('default');
            $this->client->connect();
        } catch (AuthFailedException $error) {
            throw new AuthenticationException('IMAP authentication failed.', 0, $error);
        } catch (ConnectionFailedException $error) {
            throw new ConnectionException('Could not connect to the IMAP server.', 0, $error);
        } catch (Throwable $error) {
            throw new ConnectionException('Could not establish the IMAP connection.', 0, $error);
        }
    }

    public function folders(): array
    {
        try {
            $folders = [];
            foreach ($this->requireClient()->getFolders(false) as $folder) {
                if (!$folder instanceof Folder || $folder->no_select) {
                    continue;
                }
                $folders[] = $this->folderStatus($folder);
            }
            return $folders;
        } catch (Throwable $error) {
            throw $this->translate($error, 'Could not list IMAP folders.');
        }
    }

    public function status(string $folderId): FolderStatus
    {
        try {
            return $this->folderStatus($this->requireFolder($folderId));
        } catch (Throwable $error) {
            throw $this->translate($error, 'Could not read IMAP folder status.');
        }
    }

    public function messagesAfter(string $folderId, int $lastUid, int $limit): array
    {
        try {
            $query = $this->queryAfterUid($this->requireFolder($folderId)->messages(), $lastUid)
                ->limit($limit)
                ->leaveUnread()
                ->setFetchFlags(false)
                ->fetchOrderAsc();

            $messages = [];
            foreach ($query->get() as $message) {
                if ($message instanceof Message) {
                    $messages[] = $this->mapMessage($message);
                }
            }
            usort($messages, static fn (MessageData $a, MessageData $b): int => $a->uid <=> $b->uid);
            return $messages;
        } catch (Throwable $error) {
            throw $this->translate($error, sprintf(
                'Could not fetch IMAP messages for folder "%s" after UID %d (limit %d).',
                $folderId,
                $lastUid,
                $limit,
            ));
        }
    }

    public function messagesSinceAfter(string $folderId, DateTimeImmutable $since, int $lastUid, int $limit): array
    {
        try {
            $query = $this->queryAfterUid($this->requireFolder($folderId)->messages(), $lastUid)
                ->since($since->format('d.m.Y'))
                ->limit($limit)
                ->leaveUnread()
                ->setFetchFlags(false)
                ->fetchOrderAsc();

            $messages = [];
            foreach ($query->get() as $message) {
                if ($message instanceof Message) {
                    $messages[] = $this->mapMessage($message);
                }
            }
            usort($messages, static fn (MessageData $a, MessageData $b): int => $a->uid <=> $b->uid);
            return $messages;
        } catch (Throwable $error) {
            throw $this->translate($error, 'Could not fetch historical IMAP messages.');
        }
    }

    public function downloadAttachment(string $folderId, int $uid, string $partId)
    {
        try {
            $message = $this->requireFolder($folderId)->messages()->getMessageByUid($uid);
            foreach ($message->getAttachments() as $attachment) {
                if ($attachment instanceof WebklexAttachment && (string) $attachment->getPartNumber() === $partId) {
                    $stream = tmpfile();
                    if ($stream === false || fwrite($stream, $attachment->getContent()) === false) {
                        throw new TemporarySourceException('Could not open the attachment stream.');
                    }
                    rewind($stream);
                    return $stream;
                }
            }
            throw new TemporarySourceException('The requested attachment is no longer available on IMAP.');
        } catch (Throwable $error) {
            throw $this->translate($error, 'Could not download the IMAP attachment.');
        }
    }

    private function requireClient(): Client
    {
        if ($this->client === null) {
            throw new ConnectionException('The IMAP gateway is not connected.');
        }
        return $this->client;
    }

    private function requireFolder(string $folderId): Folder
    {
        $folder = $this->requireClient()->getFolderByPath($folderId, false, true);
        if (!$folder instanceof Folder) {
            throw new TemporarySourceException(sprintf('IMAP folder "%s" is unavailable.', $folderId));
        }
        return $folder;
    }

    private function queryAfterUid(WhereQuery $query, int $lastUid): WhereQuery
    {
        return $query->where(sprintf('CUSTOM UID %d:*', $lastUid + 1));
    }

    private function folderStatus(Folder $folder): FolderStatus
    {
        $status = $folder->examine();
        $uidValidity = $status['uidvalidity'] ?? null;
        $uidNext = $status['uidnext'] ?? null;
        if (!is_int($uidValidity) || !is_int($uidNext) || $uidValidity < 1 || $uidNext < 1) {
            throw new TemporarySourceException('The IMAP server did not return UIDVALIDITY and UIDNEXT.');
        }
        return new FolderStatus($folder->path, $folder->name, $uidValidity, $uidNext - 1);
    }

    /** Maps an already fetched message; public to support MIME fixture verification. */
    public function mapMessage(Message $message): MessageData
    {
        $references = $this->stringList($message->getReferences());
        $inReplyTo = $this->attributeString($message->getInReplyTo());
        if ($inReplyTo !== null) {
            $references[] = $inReplyTo;
        }
        $date = $message->getDate()->toDate();

        $attachments = [];
        foreach ($message->getAttachments() as $attachment) {
            if (!$attachment instanceof WebklexAttachment) {
                continue;
            }
            $attachments[] = new AttachmentData(
                (string) $attachment->getPartNumber(),
                $this->mimeHeaders->decode((string) $attachment->getName()),
                (string) $attachment->getContentType(),
                is_numeric($attachment->getSize()) ? (int) $attachment->getSize() : null,
                strtolower((string) $attachment->getDisposition()) === 'inline',
                $this->nullableString($attachment->getId()),
            );
        }

        return new MessageData(
            (int) $message->getUid(),
            $this->attributeString($message->getMessageId()),
            $this->mimeHeaders->decode($this->attributeString($message->getSubject()) ?? ''),
            $this->addresses($message->getFrom()),
            $this->addresses($message->getTo()),
            $this->addresses($message->getCc()),
            array_values(array_unique($references)),
            $message->hasTextBody() ? $message->getTextBody() : null,
            $message->hasHTMLBody() ? $message->getHTMLBody() : null,
            DateTimeImmutable::createFromInterface($date),
            $attachments,
            ['raw' => $message->getHeader()?->raw],
        );
    }

    /** @return list<EmailAddress> */
    private function addresses(mixed $attribute): array
    {
        $values = $attribute instanceof Attribute ? $attribute->all() : [];
        $addresses = [];
        foreach ($values as $value) {
            if (!is_object($value)) {
                continue;
            }
            $mail = $value->mail ?? null;
            $personal = $value->personal ?? null;
            if (is_string($mail) && trim($mail) !== '') {
                $addresses[] = new EmailAddress(
                    $mail,
                    is_string($personal) ? $this->mimeHeaders->decode($personal) : null,
                );
            }
        }
        return $addresses;
    }

    /** @return list<string> */
    private function stringList(mixed $attribute): array
    {
        $values = $attribute instanceof Attribute ? $attribute->all() : [];
        return array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
    }

    private function attributeString(mixed $value): ?string
    {
        if ($value instanceof Attribute) {
            $value = $value->first();
        }
        return $this->nullableString($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $string = trim((string) $value);
        return $string !== '' ? $string : null;
    }

    private function translate(Throwable $error, string $message): ThreadMeshException
    {
        if ($error instanceof AuthenticationException || $error instanceof ConnectionException) {
            return $error;
        }
        if ($error instanceof AuthFailedException) {
            return new AuthenticationException('IMAP authentication failed.', 0, $error);
        }
        return new TemporarySourceException($this->appendCause($message, $error), 0, $error);
    }

    private function appendCause(string $message, Throwable $error): string
    {
        $detail = $this->nullableString($error->getMessage());
        if ($detail === null || $detail === $message) {
            return $message;
        }

        return sprintf('%s Cause: %s', $message, $detail);
    }
}
