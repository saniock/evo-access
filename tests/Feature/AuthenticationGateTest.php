<?php

namespace Saniock\EvoAccess\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Saniock\EvoAccess\Controllers\UsersController;
use Saniock\EvoAccess\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_controller_aborts_401_when_session_not_validated(): void
    {
        // TestCase base sets mgrValidated — clear it to simulate logged out
        unset($_SESSION['mgrValidated']);

        try {
            $this->app->make(UsersController::class);
            $this->fail('Expected HttpException was not thrown');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }
    }

    public function test_controller_aborts_401_when_internal_key_missing(): void
    {
        unset($_SESSION['mgrInternalKey']);

        try {
            $this->app->make(UsersController::class);
            $this->fail('Expected HttpException was not thrown');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }
    }

    public function test_controller_aborts_401_when_session_completely_empty(): void
    {
        $_SESSION = [];

        try {
            $this->app->make(UsersController::class);
            $this->fail('Expected HttpException was not thrown');
        } catch (HttpException $e) {
            $this->assertSame(401, $e->getStatusCode());
        }
    }
}
