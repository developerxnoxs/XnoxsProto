<?php

namespace XnoxsProto\Crypto;

class AES
{
    public static function encryptIGE(string $plaintext, string $key, string $iv): string
    {
        $padding = strlen($plaintext) % 16;
        if ($padding > 0) {
            $plaintext .= random_bytes(16 - $padding);
        }

        $iv1 = substr($iv, 0, strlen($iv) / 2);
        $iv2 = substr($iv, strlen($iv) / 2);

        $ciphertext = '';
        $blocksCount = strlen($plaintext) / 16;

        for ($blockIndex = 0; $blockIndex < $blocksCount; $blockIndex++) {
            $plaintextBlock = substr($plaintext, $blockIndex * 16, 16);
            
            $xored = $plaintextBlock ^ $iv1;
            
            $ciphertextBlock = openssl_encrypt(
                $xored,
                'AES-256-ECB',
                $key,
                OPENSSL_RAW_DATA | OPENSSL_NO_PADDING
            );
            
            $ciphertextBlock = $ciphertextBlock ^ $iv2;
            
            $iv1 = $ciphertextBlock;
            $iv2 = $plaintextBlock;
            
            $ciphertext .= $ciphertextBlock;
        }

        return $ciphertext;
    }

    public static function decryptIGE(string $ciphertext, string $key, string $iv): string
    {
        $iv1 = substr($iv, 0, strlen($iv) / 2);
        $iv2 = substr($iv, strlen($iv) / 2);

        $plaintext = '';
        $blocksCount = strlen($ciphertext) / 16;

        for ($blockIndex = 0; $blockIndex < $blocksCount; $blockIndex++) {
            $ciphertextBlock = substr($ciphertext, $blockIndex * 16, 16);
            
            $xored = $ciphertextBlock ^ $iv2;
            
            $plaintextBlock = openssl_decrypt(
                $xored,
                'AES-256-ECB',
                $key,
                OPENSSL_RAW_DATA | OPENSSL_NO_PADDING
            );
            
            $plaintextBlock = $plaintextBlock ^ $iv1;
            
            $iv1 = substr($ciphertext, $blockIndex * 16, 16);
            $iv2 = $plaintextBlock;
            
            $plaintext .= $plaintextBlock;
        }

        return $plaintext;
    }

    public static function encryptCTR(string $plaintext, string $key, string $iv): string
    {
        return openssl_encrypt($plaintext, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
    }

    public static function decryptCTR(string $ciphertext, string $key, string $iv): string
    {
        return openssl_decrypt($ciphertext, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
    }
}
