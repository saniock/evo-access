<?php

namespace Saniock\EvoAccess;

use Saniock\EvoAccess\Services\AccessService;

/**
 * Main class behind the `EvoAccess` facade.
 *
 * Kept as a thin wrapper around AccessService so the facade API
 * stays stable even as the underlying service evolves.
 */
class EvoAccess
{
    public function __construct(private readonly AccessService $access)
    {
    }

    public function service(): AccessService
    {
        return $this->access;
    }
}
