<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('approvio.tables.actions'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_request_id')
                ->constrained(config('approvio.tables.requests'))
                ->cascadeOnDelete();

            $table->foreignId('approval_request_step_id')
                ->nullable()
                ->constrained(config('approvio.tables.request_steps'))
                ->nullOnDelete();

            // Who performed the action (polymorphic).
            $table->nullableMorphs('actor');

            // What kind of action (see ActionType enum).
            $table->string('action');

            $table->text('comment')->nullable();

            $table->json('metadata')->nullable();

            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            // Append-only log: created_at only, no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['approval_request_id', 'action']);
            // Note: actor_type + actor_id index is already created by nullableMorphs() above.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('approvio.tables.actions'));
    }
};
