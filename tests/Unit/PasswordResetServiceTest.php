<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PasswordResetService();
    }

    public function test_service_returns_safe_response_for_non_existent_email(): void
    {
        Mail::fake();
        $result = $this->service->sendResetLink('ghost@daotomo.site');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['data']['success']);
        Mail::assertNothingSent();
    }

    public function test_service_rejects_invalid_reset_token(): void
    {
        $result = $this->service->resetPassword('invalid_token_123', 'newpassword123');

        $this->assertEquals(400, $result['status']);
        $this->assertFalse($result['data']['success']);
    }

    public function test_service_rejects_short_password(): void
    {
        $result = $this->service->resetPassword('validtoken123', '123');

        $this->assertEquals(400, $result['status']);
        $this->assertFalse($result['data']['success']);
    }
}
