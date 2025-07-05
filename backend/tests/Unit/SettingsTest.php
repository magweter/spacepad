<?php

namespace Tests\Unit;

use App\Helpers\Settings;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('settings helper can get and set string values', function () {
    $key = 'test_string';
    $value = 'test value';
    
    // Test setting a value
    expect(Settings::setSetting($key, $value))->toBeTrue();
    
    // Test getting the value
    expect(Settings::getSetting($key))->toBe($value);
    
    // Test getting with default value
    expect(Settings::getSetting('non_existent_key', 'default'))->toBe('default');
});

test('settings helper can handle different types', function () {
    // Test boolean
    Settings::setSetting('test_bool', true, 'boolean');
    expect(Settings::getSetting('test_bool'))->toBeTrue();
    
    // Test integer
    Settings::setSetting('test_int', 42, 'integer');
    expect(Settings::getSetting('test_int'))->toBe(42);
    
    // Test float
    Settings::setSetting('test_float', 3.14, 'float');
    expect(Settings::getSetting('test_float'))->toBe(3.14);
    
    // Test array
    $array = ['key' => 'value'];
    Settings::setSetting('test_array', $array, 'array');
    expect(Settings::getSetting('test_array'))->toBe($array);
});

test('settings helper can delete settings', function () {
    $key = 'test_delete';
    $value = 'to be deleted';
    
    // Set a value
    Settings::setSetting($key, $value);
    expect(Settings::getSetting($key))->toBe($value);
    
    // Delete the value
    expect(Settings::deleteSetting($key))->toBeTrue();
    expect(Settings::getSetting($key))->toBeNull();
    
    // Test deleting non-existent key
    expect(Settings::deleteSetting('non_existent_key'))->toBeFalse();
});

test('settings helper can get all settings', function () {
    // Set multiple settings
    Settings::setSetting('key1', 'value1');
    Settings::setSetting('key2', 'value2');
    
    $allSettings = Settings::getAllSettings();
    
    expect($allSettings)->toBeArray()
        ->toHaveCount(2)
        ->toHaveKey('key1', 'value1')
        ->toHaveKey('key2', 'value2');
});