<?php

namespace Tests\Unit;

use App\Helpers\DisplaySettings;
use App\Models\Display;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('display settings helper can get and set boolean values', function () {
    $user = User::factory()->create();
    $display = Display::factory()->create(['user_id' => $user->id]);
    
    // Test setting check-in enabled
    expect(DisplaySettings::setCheckInEnabled($display, true))->toBeTrue();
    expect(DisplaySettings::isCheckInEnabled($display))->toBeTrue();
    
    // Test setting booking enabled
    expect(DisplaySettings::setBookingEnabled($display, true))->toBeTrue();
    expect(DisplaySettings::isBookingEnabled($display))->toBeTrue();
    
    // Test default values
    $newDisplay = Display::factory()->create(['user_id' => $user->id]);
    expect(DisplaySettings::isCheckInEnabled($newDisplay))->toBeFalse();
    expect(DisplaySettings::isBookingEnabled($newDisplay))->toBeFalse();
});

test('display model convenience methods work correctly', function () {
    $user = User::factory()->create();
    $display = Display::factory()->create(['user_id' => $user->id]);
    
    // Test default values
    expect($display->isCheckInEnabled())->toBeFalse();
    expect($display->isBookingEnabled())->toBeFalse();
    
    // Test setting values
    expect($display->setCheckInEnabled(true))->toBeTrue();
    expect($display->setBookingEnabled(true))->toBeTrue();
    
    // Test getting values
    expect($display->isCheckInEnabled())->toBeTrue();
    expect($display->isBookingEnabled())->toBeTrue();
});

test('display settings can be retrieved as array', function () {
    $user = User::factory()->create();
    $display = Display::factory()->create(['user_id' => $user->id]);
    
    // Set some settings
    DisplaySettings::setCheckInEnabled($display, true);
    DisplaySettings::setBookingEnabled($display, false);
    
    $allSettings = DisplaySettings::getAllSettings($display);
    
    expect($allSettings)->toBeArray()
        ->toHaveKey('check_in_enabled', true)
        ->toHaveKey('booking_enabled', false);
}); 