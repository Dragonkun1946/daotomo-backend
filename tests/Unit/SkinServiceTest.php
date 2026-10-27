<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\User\SkinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkinServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SkinService $skinService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skinService = new SkinService();
    }

    public function test_skin_service_rejects_empty_data(): void
    {
        $user = User::factory()->create();
        $result = $this->skinService->updateUserSkin($user, null, null);

        $this->assertEquals(400, $result['status']);
        $this->assertFalse($result['data']['success']);
    }

    public function test_skin_service_accepts_valid_external_url(): void
    {
        $user = User::factory()->create();
        $result = $this->skinService->updateUserSkin($user, null, 'https://textures.minecraft.net/texture/valid_skin');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['data']['success']);
        $this->assertEquals('https://textures.minecraft.net/texture/valid_skin', $user->fresh()->skin_url);
    }

    public function test_skin_service_resets_to_steve(): void
    {
        $user = User::factory()->create(['skin_url' => 'https://some-old-skin.png']);
        $result = $this->skinService->updateUserSkin($user, null, '');

        $this->assertEquals(200, $result['status']);
        $this->assertTrue($result['data']['success']);
        $this->assertEquals('', $user->fresh()->skin_url);
    }

    public function test_skin_service_rejects_corrupted_base64(): void
    {
        $user = User::factory()->create();
        $result = $this->skinService->updateUserSkin($user, 'not-a-valid-png-header', null);

        $this->assertEquals(400, $result['status']);
        $this->assertFalse($result['data']['success']);
    }
}
