<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role')->default('member');

            // A sha256 hash of the token that goes out by email. The link is a credential,
            // so the plaintext is never stored — a database dump must not hand out
            // workspace access.
            $table->string('token', 64)->unique();

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignUlid('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One invitation per address per workspace: re-inviting rotates the existing
            // row's token rather than piling up rows.
            $table->unique(['workspace_id', 'email']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_invitations');
    }
};
