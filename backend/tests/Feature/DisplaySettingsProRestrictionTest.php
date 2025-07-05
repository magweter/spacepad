<?php

namespace Tests\Feature;

use App\Enums\UsageType;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplaySettingsProRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_pro_users_cannot_access_display_settings_page()
    {
        $user = User::factory()->create([
            'usage_type' => UsageType::BUSINESS,
            'is_unlimited' => false,
        ]);

        $display = Display::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('displays.settings.index', $display));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'Display settings are only available for Pro users.');
    }

    public function test_non_pro_users_cannot_update_display_settings()
    {
        $user = User::factory()->create([
            'usage_type' => UsageType::BUSINESS,
            'is_unlimited' => false,
        ]);

        $display = Display::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->put(route('displays.settings.update', $display), [
                'check_in_enabled' => true,
                'booking_enabled' => true,
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('error', 'Display settings are only available for Pro users.');
    }

    public function test_pro_users_can_access_display_settings_page()
    {
        $user = User::factory()->create([
            'usage_type' => UsageType::PERSONAL,
        ]);

        $display = Display::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('displays.settings.index', $display));

        $response->assertStatus(200);
        $response->assertViewIs('pages.displays.settings');
    }

    public function test_pro_users_can_update_display_settings()
    {
        $user = User::factory()->create([
            'usage_type' => UsageType::PERSONAL,
        ]);

        $display = Display::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->put(route('displays.settings.update', $display), [
                'check_in_enabled' => true,
                'booking_enabled' => false,
            ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success', 'Display settings updated successfully');

        // Verify settings were actually saved
        $this->assertTrue($display->fresh()->isCheckInEnabled());
        $this->assertFalse($display->fresh()->isBookingEnabled());
    }

    public function test_settings_button_not_visible_for_non_pro_users()
    {
        $user = User::factory()->create([
            'usage_type' => UsageType::BUSINESS,
            'is_unlimited' => false,
        ]);

        $display = Display::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('displays.settings.index');
    }

    public function test_settings_button_visible_for_pro_users()
    {
        $user = User::factory()->create([
            'usage_type' => UsageType::PERSONAL,
        ]);

        $display = Display::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee(route('displays.settings.index', $display));
    }
} 