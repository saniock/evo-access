<?php

namespace Saniock\EvoAccess\Exceptions;

use RuntimeException;

/**
 * Thrown by the access service when a user attempts an action they
 * are not permitted to perform. Host modules typically catch this
 * at the controller boundary and translate it into a 403 response.
 */
class AccessDeniedException extends RuntimeException
{
    public function __construct(
        string $message = 'Access denied.',
        public readonly ?string $permission = null,
        public readonly ?string $action = null,
        public readonly ?int $userId = null,
    ) {
        parent::__construct($message);
    }
}
