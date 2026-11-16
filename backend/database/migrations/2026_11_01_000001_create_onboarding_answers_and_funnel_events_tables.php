<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Answers to onboarding questions, and the steps a workspace takes from signing up to paying.
 *
 * Both are generic on purpose: a new question or a new funnel step is a new key in code, not a
 * new column. Keys are only ever added, never renamed, so older rows stay readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_answers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            // Who answered. Questions about the person (their role) are read per user, questions
            // about the organisation per workspace.
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('question', 64);
            // An option key, or 'skipped'. A multiple-choice answer is one row per option.
            $table->string('value', 64);
            // Free text that goes with an option, such as the product name for "something else".
            $table->string('text')->nullable();
            // Where the answer was given: the dashboard card, the onboarding flow, ...
            $table->string('source', 32);
            $table->timestamp('answered_at');
            $table->timestamps();

            $table->index(['workspace_id', 'question', 'answered_at']);
            $table->index(['question', 'value']);
        });

        Schema::create('funnel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 64);
            $table->json('properties')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['workspace_id', 'event']);
            $table->index(['event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_events');
        Schema::dropIfExists('onboarding_answers');
    }
};
