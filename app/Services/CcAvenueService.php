<?php

namespace App\Services;

class CcAvenueService
{
    public function isConfigured(): bool
    {
        return $this->merchantId() !== ''
            && $this->accessCode() !== ''
            && $this->workingKey() !== '';
    }

    public function merchantId(): string
    {
        return trim((string) config('ccavenue.merchant_id', ''));
    }

    public function accessCode(): string
    {
        return trim((string) config('ccavenue.access_code', ''));
    }

    public function gatewayUrl(): string
    {
        $mode = config('ccavenue.mode', 'live');

        return (string) config("ccavenue.gateway_urls.{$mode}", config('ccavenue.gateway_urls.live'));
    }

    public function buildCheckoutPayload(array $fields): array
    {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('CCAvenue is not configured.');
        }

        $merchantData = http_build_query(
            array_filter($fields, static fn ($value) => $value !== null && $value !== ''),
            '',
            '&',
            PHP_QUERY_RFC3986
        );

        return [
            'gateway_url' => $this->gatewayUrl(),
            'access_code' => $this->accessCode(),
            'enc_request' => $this->encrypt($merchantData),
        ];
    }

    public function decryptResponse(?string $encryptedResponse): array
    {
        if (! is_string($encryptedResponse) || trim($encryptedResponse) === '') {
            return [];
        }

        $plainText = $this->decrypt(trim($encryptedResponse));
        if ($plainText === '') {
            return [];
        }

        parse_str($plainText, $parsed);

        return is_array($parsed) ? $parsed : [];
    }

    public function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function encrypt(string $plainText): string
    {
        $key = $this->binaryKey();
        $iv = pack('C*', ...range(0, 15));
        $padded = $this->pkcs5Pad($plainText, 16);

        $encrypted = openssl_encrypt(
            $padded,
            'AES-128-CBC',
            $key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Unable to encrypt CCAvenue request.');
        }

        return bin2hex($encrypted);
    }

    private function decrypt(string $encryptedText): string
    {
        $cipherText = hex2bin($encryptedText);
        if ($cipherText === false) {
            return '';
        }

        $decrypted = openssl_decrypt(
            $cipherText,
            'AES-128-CBC',
            $this->binaryKey(),
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            pack('C*', ...range(0, 15))
        );

        if ($decrypted === false) {
            return '';
        }

        return $this->pkcs5Unpad($decrypted);
    }

    private function binaryKey(): string
    {
        return pack('H*', md5($this->workingKey()));
    }

    private function workingKey(): string
    {
        return trim((string) config('ccavenue.working_key', ''));
    }

    private function pkcs5Pad(string $plainText, int $blockSize): string
    {
        $pad = $blockSize - (strlen($plainText) % $blockSize);

        return $plainText . str_repeat(chr($pad), $pad);
    }

    private function pkcs5Unpad(string $plainText): string
    {
        $length = strlen($plainText);
        if ($length === 0) {
            return $plainText;
        }

        $pad = ord($plainText[$length - 1]);
        if ($pad < 1 || $pad > 16) {
            return $plainText;
        }

        return substr($plainText, 0, $length - $pad);
    }
}