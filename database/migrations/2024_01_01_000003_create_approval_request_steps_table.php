<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('approvio.tables.request_steps'), function (Blueprint $table) {
            $table->id();

            $table->foreignId('approval_request_id')
                ->constrained(config('approvio.tables.requests'))
                ->cascadeOnDelete();

            // Order within the workflow.
            $table->unsignedInteger('step_index');

            // Human-friendly name (matches the workflow definition).
            $table->string('step_name');

            // sequential | parallel
            $table->string('type')->default('sequential');

            // Quorum rules — used for parallel steps in v0.2+. Stored
            // now so the schema is stable.
            $table->string('quorum_rule')->default('any'); // any|all|n_of_m
            $table->unsignedInteger('quorum_count')->nullable();

            // Status of this step.
            $table->string('status')->default('pending')->index();

            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('deadline_at')->nullable();

            // Frozen step config (resolvers, conditions) for posterity.
            $table->json('config')->nullable();

            $table->timestamps();

            $table->unique(['approval_request_id', 'step_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('approvio.tables.request_steps'));
    }
};
