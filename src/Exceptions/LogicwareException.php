<?php

declare(strict_types=1);

namespace Logicware\Connect\Exceptions;

/**
 * Base class for all SDK-thrown exceptions. Consumers can catch this once
 * to trap both API and network failures uniformly.
 */
class LogicwareException extends \RuntimeException
{
}
