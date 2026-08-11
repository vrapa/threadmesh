<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RecipientTemplateTest extends TestCase
{
    public function testOverviewAndDetailRenderRecipientAddresses(): void
    {
        $templates = [
            __DIR__ . '/../app/Presenters/templates/Dashboard/default.latte',
            __DIR__ . '/../app/Presenters/templates/Dashboard/detail.latte',
        ];

        foreach ($templates as $template) {
            $source = file_get_contents($template);
            self::assertIsString($source);
            self::assertStringContainsString("$" . "email['recipients']", $source);
            self::assertStringContainsString("$" . "recipient['address']", $source);
            self::assertStringContainsString('Komu', $source);
        }
    }
}
