<?php

namespace XnoxsProto\Network;

use XnoxsProto\Crypto\RSA;
use XnoxsProto\Crypto\AES;
use XnoxsProto\Crypto\AuthKey;
use XnoxsProto\Helpers\Helpers;
use XnoxsProto\TL\BinaryReader;
use XnoxsProto\TL\Types\ResPQ;
use XnoxsProto\TL\Types\PQInnerData;
use XnoxsProto\TL\Types\ServerDHParamsOk;
use XnoxsProto\TL\Types\ServerDHParamsFail;
use XnoxsProto\TL\Types\ServerDHInnerData;
use XnoxsProto\TL\Types\ClientDHInnerData;
use XnoxsProto\TL\Types\DhGenOk;
use XnoxsProto\TL\Types\DhGenRetry;
use XnoxsProto\TL\Types\DhGenFail;
use XnoxsProto\TL\Functions\ReqPqMultiRequest;
use XnoxsProto\TL\Functions\ReqDHParamsRequest;
use XnoxsProto\TL\Functions\SetClientDHParamsRequest;

class Authenticator
{
    private MTProtoPlainSender $sender;
    private int $timeOffset = 0;

    public function __construct($ipOrConnection, ?int $port = null)
    {
        if ($ipOrConnection instanceof Connection) {
            $transport = $ipOrConnection->getTransport();
        } elseif (is_string($ipOrConnection) && $port !== null) {
            $transport = new TcpAbridged($ipOrConnection, $port);
        } else {
            throw new \InvalidArgumentException('Invalid constructor arguments');
        }
        
        $this->sender = new MTProtoPlainSender($transport);
        RSA::initDefaultKeys();
    }
    
    public function getTimeOffset(): int
    {
        return $this->timeOffset;
    }

    public function doAuthentication(): AuthKey
    {
        $nonce = Helpers::generateRandomBytes(16);
        
        $reqPq = new ReqPqMultiRequest($nonce);
        $reqPqBytes = $reqPq->toBytes();
        
        $response = $this->sender->sendRecv($reqPqBytes);
        $reader = new BinaryReader($response['data']);
        
        $constructorId = $reader->readInt();
        if ($constructorId !== ResPQ::CONSTRUCTOR_ID) {
            throw new \RuntimeException(sprintf(
                'Expected ResPQ constructor 0x%08x, got 0x%08x',
                ResPQ::CONSTRUCTOR_ID,
                $constructorId
            ));
        }
        
        $resPq = ResPQ::fromReader($reader);
        
        if ($resPq->nonce !== $nonce) {
            throw new \RuntimeException('Nonce mismatch in ResPQ');
        }
        
        $pqInt = Helpers::getInt($resPq->pq);
        
        [$p, $q] = Helpers::factorize($pqInt);
        
        $pBytes = Helpers::getByteArray(gmp_init($p));
        $qBytes = Helpers::getByteArray(gmp_init($q));
        
        $newNonce = Helpers::generateRandomBytes(32);
        
        $pqInnerData = new PQInnerData(
            $resPq->pq,
            $pBytes,
            $qBytes,
            $resPq->nonce,
            $resPq->serverNonce,
            $newNonce
        );
        
        $pqInnerBytes = $pqInnerData->toBytes();
        
        $cipherText = null;
        $targetFingerprint = null;
        
        foreach ($resPq->serverPublicKeyFingerprints as $fingerprint) {
            $cipherText = RSA::encrypt($fingerprint, $pqInnerBytes, false);
            if ($cipherText !== null) {
                $targetFingerprint = $fingerprint;
                break;
            }
        }
        
        if ($cipherText === null) {
            foreach ($resPq->serverPublicKeyFingerprints as $fingerprint) {
                $cipherText = RSA::encrypt($fingerprint, $pqInnerBytes, true);
                if ($cipherText !== null) {
                    $targetFingerprint = $fingerprint;
                    break;
                }
            }
        }
        
        if ($cipherText === null) {
            throw new \RuntimeException('No matching RSA key found');
        }
        
        $reqDHParams = new ReqDHParamsRequest(
            $resPq->nonce,
            $resPq->serverNonce,
            $pBytes,
            $qBytes,
            $targetFingerprint,
            $cipherText
        );
        
        $response = $this->sender->sendRecv($reqDHParams->toBytes());
        $reader = new BinaryReader($response['data']);
        $serverDHParams = $reader->readObject();
        
        if ($serverDHParams instanceof ServerDHParamsFail) {
            throw new \RuntimeException('Server returned DH params fail');
        }
        
        if (!($serverDHParams instanceof ServerDHParamsOk)) {
            throw new \RuntimeException('Expected ServerDHParamsOk');
        }
        
        if ($serverDHParams->nonce !== $resPq->nonce) {
            throw new \RuntimeException('Nonce mismatch in ServerDHParams');
        }
        
        if ($serverDHParams->serverNonce !== $resPq->serverNonce) {
            throw new \RuntimeException('Server nonce mismatch in ServerDHParams');
        }
        
        [$key, $iv] = Helpers::generateKeyDataFromNonce($resPq->serverNonce, $newNonce);
        
        if (strlen($serverDHParams->encryptedAnswer) % 16 !== 0) {
            throw new \RuntimeException('Invalid encrypted answer length');
        }
        
        $plainText = AES::decryptIGE($serverDHParams->encryptedAnswer, $key, $iv);
        
        $reader = new BinaryReader($plainText);
        $hashSum = $reader->read(20);
        $serverDHInner = $reader->readObject();
        
        if (!($serverDHInner instanceof ServerDHInnerData)) {
            throw new \RuntimeException('Expected ServerDHInnerData');
        }
        
        if ($serverDHInner->nonce !== $resPq->nonce) {
            throw new \RuntimeException('Nonce mismatch in ServerDHInnerData');
        }
        
        if ($serverDHInner->serverNonce !== $resPq->serverNonce) {
            throw new \RuntimeException('Server nonce mismatch in ServerDHInnerData');
        }
        
        $dhPrime = Helpers::getInt($serverDHInner->dhPrime, false);
        $g = gmp_init($serverDHInner->g);
        $gA = Helpers::getInt($serverDHInner->gA, false);
        $this->timeOffset = $serverDHInner->serverTime - time();
        
        $b = Helpers::getInt(Helpers::generateRandomBytes(256), false);
        $gB = gmp_powm($g, $b, $dhPrime);
        $gab = gmp_powm($gA, $b, $dhPrime);
        
        $one = gmp_init(1);
        $dhPrimeMinusOne = gmp_sub($dhPrime, $one);
        
        if (gmp_cmp($g, $one) <= 0 || gmp_cmp($g, $dhPrimeMinusOne) >= 0) {
            throw new \RuntimeException('g is not within (1, dh_prime - 1)');
        }
        
        if (gmp_cmp($gA, $one) <= 0 || gmp_cmp($gA, $dhPrimeMinusOne) >= 0) {
            throw new \RuntimeException('g_a is not within (1, dh_prime - 1)');
        }
        
        if (gmp_cmp($gB, $one) <= 0 || gmp_cmp($gB, $dhPrimeMinusOne) >= 0) {
            throw new \RuntimeException('g_b is not within (1, dh_prime - 1)');
        }
        
        $safetyRange = gmp_pow(2, 2048 - 64);
        if (gmp_cmp($gA, $safetyRange) < 0 || gmp_cmp($gA, gmp_sub($dhPrime, $safetyRange)) > 0) {
            throw new \RuntimeException('g_a is not within safety range');
        }
        
        if (gmp_cmp($gB, $safetyRange) < 0 || gmp_cmp($gB, gmp_sub($dhPrime, $safetyRange)) > 0) {
            throw new \RuntimeException('g_b is not within safety range');
        }
        
        $clientDHInner = new ClientDHInnerData(
            $resPq->nonce,
            $resPq->serverNonce,
            0,
            Helpers::getByteArray($gB)
        );
        
        $clientDHInnerBytes = $clientDHInner->toBytes();
        $clientDHInnerHashed = sha1($clientDHInnerBytes, true) . $clientDHInnerBytes;
        
        $clientDHEncrypted = AES::encryptIGE($clientDHInnerHashed, $key, $iv);
        
        $setClientDH = new SetClientDHParamsRequest(
            $resPq->nonce,
            $resPq->serverNonce,
            $clientDHEncrypted
        );
        
        $response = $this->sender->sendRecv($setClientDH->toBytes());
        $reader = new BinaryReader($response['data']);
        $dhGen = $reader->readObject();
        
        if ($dhGen->nonce !== $resPq->nonce) {
            throw new \RuntimeException('Nonce mismatch in DH result');
        }
        
        if ($dhGen->serverNonce !== $resPq->serverNonce) {
            throw new \RuntimeException('Server nonce mismatch in DH result');
        }
        
        $authKey = new AuthKey(Helpers::getByteArray($gab));
        
        if ($dhGen instanceof DhGenOk) {
            $newNonceHash = $authKey->calcNewNonceHash($newNonce, 1);
            if ($dhGen->newNonceHash1 !== $newNonceHash) {
                throw new \RuntimeException('New nonce hash mismatch');
            }
            
            return $authKey;
            
        } elseif ($dhGen instanceof DhGenRetry) {
            throw new \RuntimeException('DH generation retry required');
        } elseif ($dhGen instanceof DhGenFail) {
            throw new \RuntimeException('DH generation failed');
        } else {
            throw new \RuntimeException('Unknown DH generation result');
        }
    }
}
