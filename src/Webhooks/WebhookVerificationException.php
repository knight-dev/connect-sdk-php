<?php

declare(strict_types=1);

namespace Logicware\Connect\Webhooks;

use RuntimeException;

class WebhookVerificationException extends RuntimeException
{
    public const MISSING_SIGNATURE = 'MISSING_SIGNATURE';
    public const MISSING_TIMESTAMP = 'MISSING_TIMESTAMP';
    public const INVALID_SIGNATURE_FORMAT = 'INVALID_SIGNATURE_FORMAT';
    public const SIGNATURE_MISMATCH = 'SIGNATURE_MISMATCH';
    public const TIMESTAMP_OUT_OF_TOLERANCE = 'TIMESTAMP_OUT_OF_TOLERANCE';
    public const INVALID_PAYLOAD = 'INVALID_PAYLOAD';

    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
