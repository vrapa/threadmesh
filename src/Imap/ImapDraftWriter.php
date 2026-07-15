<?php

declare(strict_types=1);

namespace ThreadMesh\Imap;

use RuntimeException;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;

final class ImapDraftWriter
{
    /**
     * @param array<string, mixed> $email
     * @param array<string, mixed> $draft
     */
    public function save(ImapConfiguration $configuration, string $folderPath, array $email, array $draft): string
    {
        if (trim($folderPath) === '') {
            throw new RuntimeException('Account configuration field "draftFolder" is required for IMAP draft publishing.');
        }
        try {
            $manager = new ClientManager(['accounts' => ['default' => [
                'host' => $configuration->host,
                'port' => $configuration->port,
                'protocol' => 'imap',
                'encryption' => $configuration->encryption === 'starttls' ? 'tls' : $configuration->encryption,
                'validate_cert' => $configuration->validateCertificate,
                'username' => $configuration->username,
                'password' => $configuration->password,
                'authentication' => null,
            ]]]);
            $client = $manager->account('default');
            $client->connect();
            try {
                $folder = $client->getFolderByPath($folderPath, false, true);
                if (!$folder instanceof Folder) {
                    throw new RuntimeException(sprintf('Configured IMAP draft folder "%s" was not found.', $folderPath));
                }
                $folder->appendMessage($this->mime($configuration->username, $email, $draft), ['\\Draft']);
            } finally {
                $client->disconnect();
            }
            return 'imap:' . $folderPath;
        } catch (RuntimeException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new RuntimeException('The draft could not be stored on IMAP.', 0, $error);
        }
    }

    /**
     * @param array<string, mixed> $email
     * @param array<string, mixed> $draft
     */
    private function mime(string $from, array $email, array $draft): string
    {
        $author = $email['author'] ?? null;
        $to = is_array($author) ? ($author['address'] ?? null) : null;
        if (!is_string($to) || filter_var($to, FILTER_VALIDATE_EMAIL) === false || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Draft sender or recipient email address is invalid.');
        }
        $subject = $this->header($draft['subject'] ?? null, 'subject');
        $body = $draft['body_text'] ?? null;
        if (!is_string($body)) {
            throw new RuntimeException('Draft body is invalid.');
        }
        $metadata = $email['metadata'] ?? null;
        $messageId = is_array($metadata) && is_string($metadata['messageId'] ?? null) ? trim($metadata['messageId']) : null;
        $headers = [
            'Date: ' . gmdate(DATE_RFC2822),
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $subject,
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@threadmesh.local>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
        ];
        if ($messageId !== null && preg_match('/[\r\n]/', $messageId) !== 1) {
            $normalized = trim($messageId, '<>');
            $headers[] = 'In-Reply-To: <' . $normalized . '>';
            $headers[] = 'References: <' . $normalized . '>';
        }
        return implode("\r\n", $headers) . "\r\n\r\n" . quoted_printable_encode($body) . "\r\n";
    }

    private function header(mixed $value, string $name): string
    {
        if (!is_string($value) || trim($value) === '' || preg_match('/[\r\n]/', $value) === 1) {
            throw new RuntimeException(sprintf('Draft %s is invalid.', $name));
        }
        return mb_encode_mimeheader($value, 'UTF-8', 'Q', "\r\n");
    }
}
