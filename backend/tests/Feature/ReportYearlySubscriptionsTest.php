<?php

use App\Enums\WorkspaceRole;
use App\Events\WorkspaceUsageChanged;
use App\Models\Display;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * Before this feature, the usage push resized yearly subscriptions too. This lists where
 * the quantity at Lemon Squeezy no longer matches usage, and changes nothing.
 */
beforeEach(function () {
    config([
        'settings.is_self_hosted' => false,
        'settings.pro_switch_yearly_variant_id' => '12',
        'settings.pro_yearly_variant_ids' => ['22'],
        'lemon-squeezy.api_key' => 'test-key',
    ]);

    cache()->flush();
    Event::fake([WorkspaceUsageChanged::class]);

    Http::fake([
        '*lemonsqueezy.com/v1/subscriptions/*' => Http::response([
            'data' => ['attributes' => ['subscription_items' => [['id' => 'item_1']]]],
        ]),
        '*lemonsqueezy.com/v1/subscription-items/*' => Http::response([
            'data' => ['attributes' => ['quantity' => 5]],
        ]),
    ]);
});

function reportedWorkspace(string $name, string $variant, int $displays): Workspace
{
    $owner = User::factory()->active()->create();
    $workspace = Workspace::factory()->create(['name' => $name]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'role' => WorkspaceRole::OWNER]);
    Display::factory()->count($displays)->create(['workspace_id' => $workspace->id]);

    DB::table('lemon_squeezy_subscriptions')->insert([
        'billable_id' => $workspace->id,
        'billable_type' => $workspace->getMorphClass(),
        'type' => 'default',
        'lemon_squeezy_id' => 'sub_'.$name,
        'status' => 'active',
        'product_id' => '1',
        'variant_id' => $variant,
        'renews_at' => now()->addMonths(3),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $workspace;
}

test('every yearly workspace is listed with usage, paid quantity and renewal', function () {
    reportedWorkspace('Drifted', '22', 3);
    reportedWorkspace('Matching', '12', 5);
    reportedWorkspace('Monthly', '1', 2);

    Artisan::call('app:report-yearly-subscriptions');
    $output = Artisan::output();

    expect($output)->toContain('Drifted')
        ->and($output)->toContain('Matching')
        ->and($output)->not->toContain('Monthly')
        ->and($output)->toContain(now()->addMonths(3)->toDateString())
        ->and(substr_count($output, 'mismatch'))->toBe(1);

    Http::assertNotSent(fn (Request $r) => $r->method() !== 'GET');
});
