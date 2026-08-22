<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historic record of a workspace's licence-count / MRR change.
 *
 * Rows are written by RecordBillingChange the instant a workspace's billable usage
 * (displays + boards×2) moves, and the MRR side is filled in afterwards by
 * app:refresh-analytics --mrr for subscriptions whose price only Lemon Squeezy knows.
 *
 * The user columns are the billing contact at the moment of the change, denormalised so
 * the trail still reads properly after they leave.
 */
class BillingChange extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'name',
        'workspace_id',
        'workspace_name',
        'previous_displays_count',
        'new_displays_count',
        'previous_boards_count',
        'new_boards_count',
        'previous_license_count',
        'new_license_count',
        'license_delta',
        'previous_mrr',
        'new_mrr',
        'mrr_delta',
        'change_type',
        'subscription_status',
        'detected_at',
    ];

    protected $casts = [
        'previous_displays_count' => 'integer',
        'new_displays_count' => 'integer',
        'previous_boards_count' => 'integer',
        'new_boards_count' => 'integer',
        'previous_license_count' => 'integer',
        'new_license_count' => 'integer',
        'license_delta' => 'integer',
        'previous_mrr' => 'decimal:2',
        'new_mrr' => 'decimal:2',
        'mrr_delta' => 'decimal:2',
        'detected_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
