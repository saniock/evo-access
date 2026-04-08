<?php

namespace Saniock\EvoAccess\Tests\Unit;

use Saniock\EvoAccess\Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_service_provider_loads(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(\Saniock\EvoAccess\EvoAccessServiceProvider::class));
    }

    public function test_facade_resolves(): void
    {
        $service = $this->app->make(\Saniock\EvoAccess\Services\AccessService::class);
        $this->assertInstanceOf(\Saniock\EvoAccess\Services\AccessService::class, $service);
    }
}
