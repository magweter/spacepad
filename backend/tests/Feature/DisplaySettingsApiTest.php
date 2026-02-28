<?php

namespace Tests\Feature;

use App\Enums\UsageType;
use App\Helpers\DisplaySettings;
use App\Models\Device;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplaySettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_api_includes_settings()
    {
        $user = User::factory()->create([
            'usage_type' => UsageType::PERSONAL,
        ]);
        $workspace = $user->primaryWorkspace();

        $display = Display::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        $device = Device::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'display_id' => $display->id,
        ]);

        // Set some display settings
        DisplaySettings::setCheckInEnabled($display, true);
        DisplaySettings::setBookingEnabled($display, false);

        $response = $this->actingAs($device)
            ->getJson('/api/displays');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'settings' => [
                        'check_in_enabled',
                        'booking_enabled',
                    ],
                ],
            ],
        ]);

        $displayData = $response->json('data.0');
        $this->assertTrue($displayData['settings']['check_in_enabled']);
        $this->assertFalse($displayData['settings']['booking_enabled']);
    }

    public function test_display_api_includes_default_settings_when_none_set()
    {
        $user = User::factory()->create([
            'usage_type' => UsageType::PERSONAL,
        ]);
        $workspace = $user->primaryWorkspace();

        $display = Display::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        $device = Device::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'display_id' => $display->id,
        ]);

        $response = $this->actingAs($device)
            ->getJson('/api/displays');

        $response->assertStatus(200);

        $displayData = $response->json('data.0');
        $this->assertFalse($displayData['settings']['check_in_enabled']);
        $this->assertFalse($displayData['settings']['booking_enabled']);
    }

    public function test_display_settings_are_encrypted_in_database()
    {
        $user = User::factory()->create([
            'usage_type' => UsageType::PERSONAL,
        ]);
        $workspace = $user->primaryWorkspace();

        $display = Display::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
        Device::factory()->create([
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'display_id' => $display->id,
        ]);

        // Set display settings
        DisplaySettings::setCheckInEnabled($display, true);
        DisplaySettings::setBookingEnabled($display, true);

        // Check that the raw database values are encrypted
        $displaySetting = $display->settings()->where('key', 'check_in_enabled')->first();
        $this->assertNotNull($displaySetting);
        $this->assertNotEquals('true', $displaySetting->getRawOriginal('value'));
        $this->assertTrue($displaySetting->value); // Decrypted value should be true
    }
}
