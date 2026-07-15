<?php

declare(strict_types=1);

namespace ThreadMesh\Storage;

use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;

final class SecretCipher
{
    public function __construct(
        #[SensitiveParameter]
        private readonly string $key,
    ) {
        if (strlen($key) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new InvalidArgumentException('THREADMESH_MASTER_KEY must decode to exactly 32 bytes.');
        }
    }

    public static function fromEnvironment(string $name = 'THREADMESH_MASTER_KEY'): self
    {
        $encoded = getenv($name);
        if (!is_string($encoded) || $encoded === '') {
            throw new RuntimeException(sprintf('%s is required.', $name));
        }
        $key = base64_decode($encoded, true);
        if ($key === false) {
            throw new RuntimeException(sprintf('%s must be valid base64.', $name));
        }
        return new self($key);
    }

    public function encrypt(#[SensitiveParameter] string $plaintext, string $context): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, $context, $nonce, $this->key);
        return 'v1.' . rtrim(strtr(base64_encode($nonce . $ciphertext), '+/', '-_'), '=');
    }

    public function decrypt(string $payload, string $context): string
    {
        if (!str_starts_with($payload, 'v1.')) {
            throw new RuntimeException('Encrypted secret format is unsupported.');
        }
        $encoded = strtr(substr($payload, 3), '-_', '+/');
        $remainder = strlen($encoded) % 4;
        if ($remainder !== 0) {
            $encoded .= str_repeat('=', 4 - $remainder);
        }
        $binary = base64_decode($encoded, true);
        $nonceSize = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if ($binary === false || strlen($binary) <= $nonceSize) {
            throw new RuntimeException('Encrypted secret is malformed.');
        }
        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($binary, $nonceSize),
            $context,
            substr($binary, 0, $nonceSize),
            $this->key,
        );
        if ($plaintext === false) {
            throw new RuntimeException('Encrypted secret authentication failed.');
        }
        return $plaintext;
    }
}
