<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('approvio.tables.workflows'), function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('version')->default(1);

            // Which Eloquent model this workflow applies to
            $table->string('approvable_type');

            // Tenant scope. Null = global workflow available to all tenants.
            $table->nullableMorphs('tenant');

            // The workflow definition as JSON. Mirrors the shape produced
            // by code-defined workflows so the engine treats them uniformly.
            $table->json('definition');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['slug', 'version', 'tenant_type', 'tenant_id'], 'approvio_workflows_slug_version_tenant_unique');
            $table->index(['approvable_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('approvio.tables.workflows'));
    }
};
