<?php

declare(strict_types=1);

namespace ThreadMesh\Tests\Scripts;

use PHPUnit\Framework\TestCase;

final class ConfigureImapAccountScriptTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        $contents = file_get_contents(__DIR__ . '/../../scripts/configure-imap-account.ps1');
        self::assertIsString($contents);
        $this->script = $contents;
    }

    public function testGmailPresetUsesSecureImapSettingsAndOnlyInbox(): void
    {
        self::assertStringContainsString('[switch] $Gmail', $this->script);
        self::assertStringContainsString('imap.gmail.com', $this->script);
        self::assertStringContainsString('$port = 993', $this->script);
        self::assertStringContainsString('$encryption = \'ssl\'', $this->script);
        self::assertStringContainsString('$validateCertificate = $true', $this->script);
        self::assertStringContainsString(
            '$streamIds = @([string] $folders[$inboxDefault - 1].id)',
            $this->script,
        );
    }

    public function testGmailAppPasswordRemainsHiddenAndIsNotStoredInTheScript(): void
    {
        self::assertStringContainsString(
            'Read-Host $secretPrompt -AsSecureString',
            $this->script,
        );
        self::assertStringNotContainsString('app-password', $this->script);
    }

    public function testDefaultEnvFileIsResolvedAfterParameterBinding(): void
    {
        self::assertStringContainsString('$EnvFile = \'\'', $this->script);
        self::assertStringContainsString(
            '$scriptDirectory = Split-Path -Parent $PSCommandPath',
            $this->script,
        );
        self::assertStringNotContainsString(
            'Split-Path -Parent $PSScriptRoot',
            $this->script,
        );
    }
}
