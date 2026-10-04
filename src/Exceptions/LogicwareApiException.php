<?php

declare(strict_types=1);

namespace Logicware\Connect\Exceptions;

/**
 * Thrown for any non-2xx response from the Logicware API. Exposes HTTP status,
 * the server-emitted X-Request-Id, and the structured error body.
 */
class LogicwareApiException extends LogicwareException
{
    public function __construct(
        string $message,
        private readonly int $status,
        private readonly ?string $errorCode = null,
        private readonly ?string $requestId = null,
        private readonly mixed $details = null,
    ) {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getDetails(): mixed
    {
        return $this->details;
    }

    /**
     * True for 5xx and 429 — safe to retry with backoff. Other 4xx errors
     * (401, 403, 404, 422, etc.) are not retryable without changing the request.
     */
    public function isRetryable(): bool
    {
        return $this->status === 429 || $this->status >= 500;
    }
}
