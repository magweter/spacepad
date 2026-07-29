<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historic record of a user's license-count / MRR change.
 *
 * Rows are written by the app:refresh-analytics command whenever a user's billable
 * usage (displays + boards×2) moves between snapshots. Inserts use DB::table() in
 * that command; this model is for reading (admin panel, tests).
 */
class BillingChange extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'name',
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
}
