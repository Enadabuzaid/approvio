<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('approvio.tables.requests'), function (Blueprint $table) {
            $table->id();

            // Workflow identity is captured by slug+version, not foreign key,
            // so historical requests survive workflow edits/deletes.
            $table->string('workflow_slug');
            $table->unsignedInteger('workflow_version');

            // The model being approved (polymorphic).
            $table->morphs('approvable');

            // Tenant scope.
            $table->nullableMorphs('tenant');

            // Who submitted the request (polymorphic — could be a User,
            // an admin, or even an external service).
            $table->nullableMorphs('requester');

            // High-level status.
            $table->string('status')->default('pending')->index();

            // Pointer to the currently active step (0-indexed).
            $table->unsignedInteger('current_step_index')->default(0);

            // Snapshot of the model state at submission time.
            // Survives mutations to the underlying record.
            $table->json('snapshot')->nullable();

            // Free-form context for use in conditions/notifications
            // (e.g., 'urgent' => true, 'amount' => 5000).
            $table->json('context')->nullable();

            // For DraftApproval strategy: the diff to apply on approval.
            $table->json('pending_changes')->nullable();

            // Strategy class actually used (for audit / late strategy migrations).
            $table->string('strategy')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id', 'status']);
            $table->index(['workflow_slug', 'workflow_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('approvio.tables.requests'));
    }
};
