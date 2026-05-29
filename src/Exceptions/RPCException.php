<?php

namespace XnoxsProto\Exceptions;

class RPCException extends \RuntimeException
{
    public int $errorCode;
    public string $errorMessage;

    public function __construct(int $errorCode, string $errorMessage)
    {
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        parent::__construct("RPC Error $errorCode: $errorMessage");
    }
}
