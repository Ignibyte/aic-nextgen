<?php

declare(strict_types=1);

namespace AIC\Auth\Domain;

use function base64_encode;
use function chr;
use function ord;
use function strlen;
use function substr;

/**
 * The DER an id token's signature check needs (#343), built from bytes: a
 * public key's SubjectPublicKeyInfo as PEM, from an RSA key's modulus and
 * exponent (RFC 8017) or a P-256 key's coordinates (RFC 5480), and an ES256
 * signature's `r‖s` as the DER `SEQUENCE { r, s }` openssl verifies (RFC 7518
 * 3.4). Only DER lengths below 65,536 bytes are written, enough for any key.
 */
final readonly class PublicKeyDer
{
    /**
     * `AlgorithmIdentifier { rsaEncryption, NULL }`.
     */
    private const string RSA = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";

    /**
     * `AlgorithmIdentifier { id-ecPublicKey, prime256v1 }`.
     */
    private const string P256 = "\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

    /**
     * @param string $modulus  The modulus, big-endian bytes.
     * @param string $exponent The public exponent, big-endian bytes.
     *
     * @return string The key as a PEM `PUBLIC KEY`.
     */
    public function rsa(string $modulus, string $exponent): string
    {
        $rsaPublicKey = self::tagged(0x30, self::integer($modulus) . self::integer($exponent));

        return self::pem(self::tagged(0x30, self::RSA . self::tagged(0x03, "\x00" . $rsaPublicKey)));
    }

    /**
     * @param string $pointX The x coordinate, 32 bytes.
     * @param string $pointY The y coordinate, 32 bytes.
     *
     * @return string The key as a PEM `PUBLIC KEY`.
     */
    public function p256(string $pointX, string $pointY): string
    {
        return self::pem(self::tagged(0x30, self::P256 . self::tagged(0x03, "\x00\x04" . $pointX . $pointY)));
    }

    /**
     * @param string $signature An ES256 signature, `r` then `s`, 32 bytes each.
     *
     * @return string It as `SEQUENCE { INTEGER r, INTEGER s }`.
     */
    public function ecdsa(string $signature): string
    {
        return self::tagged(0x30, self::integer(substr($signature, 0, 32)) . self::integer(substr($signature, 32, 32)));
    }

    /**
     * @param string $bytes An unsigned big-endian number.
     *
     * @return string It as a DER `INTEGER`: leading zeros dropped, one zero added when its high bit is set.
     */
    private static function integer(string $bytes): string
    {
        $start = 0;
        $last = strlen($bytes) - 1;

        while ($start < $last && ord(substr($bytes, $start, 1)[0]) === 0) {
            $start += 1;
        }

        $number = substr($bytes, $start);

        return self::tagged(0x02, ord(substr($number, 0, 1)[0]) >= 0x80 ? "\x00" . $number : $number);
    }

    /**
     * @param int<0, 255> $tag  The DER tag.
     * @param string      $body Its contents.
     *
     * @return string The tag, the length and the contents.
     */
    private static function tagged(int $tag, string $body): string
    {
        $length = strlen($body);
        $written = match (true) {
            $length < 0x80 => chr($length & 0x7f),
            $length < 0x100 => "\x81" . chr($length & 0xff),
            default => "\x82" . chr(($length >> 8) & 0xff) . chr($length & 0xff),
        };

        return chr($tag) . $written . $body;
    }

    /**
     * @param string $der A SubjectPublicKeyInfo.
     *
     * @return string It as PEM, 64 base64 characters a line.
     */
    private static function pem(string $der): string
    {
        $base64 = base64_encode($der);
        $lines = '';

        for ($offset = 0; $offset < strlen($base64); $offset += 64) {
            $lines .= substr($base64, $offset, 64) . "\n";
        }

        return "-----BEGIN PUBLIC KEY-----\n" . $lines . "-----END PUBLIC KEY-----\n";
    }
}
