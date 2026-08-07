<?php

declare(strict_types=1);

use ThreadMesh\Dashboard\Model\EmailHtmlSanitizer;

require dirname(__DIR__) . '/vendor/autoload.php';

$sanitizer = new EmailHtmlSanitizer();
$clean = $sanitizer->sanitize(<<<'HTML'
<meta http-equiv="refresh" content="0;url=https://tracker.invalid">
<script>alert(1)</script>
<img src="https://tracker.invalid/pixel">
<a href="https://tracker.invalid">link</a>
<p onclick="alert(1)" style="color:red">Safe</p>
HTML);

if (preg_match('/(?:script|meta|img|href|onclick|tracker\.invalid)/i', $clean) === 1) {
    fwrite(STDERR, "Unsafe HTML survived purification.\n");
    exit(1);
}
if (!str_contains($clean, '<p>Safe</p>')) {
    fwrite(STDERR, "Safe formatted content was removed.\n");
    exit(1);
}

echo "Email HTML sanitizer test passed.\n";
