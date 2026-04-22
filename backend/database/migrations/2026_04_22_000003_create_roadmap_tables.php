<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roadmap_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('considering');
            $table->date('expected_at')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('roadmap_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roadmap_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['roadmap_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roadmap_votes');
        Schema::dropIfExists('roadmap_items');
    }
};
