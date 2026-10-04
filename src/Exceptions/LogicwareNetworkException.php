<?php

declare(strict_types=1);

namespace Logicware\Connect\Exceptions;

/**
 * Thrown when the HTTP request never reached the server (DNS, TLS, connection
 * reset, client-side timeout). No HTTP status is available.
 */
class LogicwareNetworkException extends LogicwareException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
