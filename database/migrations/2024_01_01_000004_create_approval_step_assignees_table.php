<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('approvio.tables.step_assignees'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_request_step_id')
                ->constrained(config('approvio.tables.request_steps'))
                ->cascadeOnDelete();

            // Polymorphic — usually a User, but could be a Team, Role, etc.
            $table->morphs('assignee');

            // How this assignee was resolved (for audit/debugging).
            // direct | role | relationship | resolver
            $table->string('assigned_via')->default('direct');

            // Per-assignee status.
            $table->string('status')->default('pending')->index();

            $table->timestamp('acted_at')->nullable();

            // For delegation: who this assignee passed responsibility to.
            $table->nullableMorphs('delegated_to');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('approvio.tables.step_assignees'));
    }
};
