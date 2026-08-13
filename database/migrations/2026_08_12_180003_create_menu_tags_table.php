<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu tag generation records (contract 01). Enum columns are plain strings
 * cast to native PHP enums on the model; `parameters` is the full DTO
 * snapshot and the source for duplication.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Migrated onto user_id when the guest registers (spec §7.2).
            $table->uuid('guest_token')->nullable();
            $table->string('label')->nullable();
            // Origin preset — a custom configuration always starts from one.
            $table->string('preset');
            $table->boolean('customized')->default(false);
            $table->foreignId('logo_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->json('parameters');
            $table->string('status')->default('queued');
            // Relative to the 'stl' disk; accent only with mode=inlay.
            $table->string('stl_path')->nullable();
            $table->string('stl_accent_path')->nullable();
            // Every stdout key of the engine (contract 03 §4) — feeds the
            // printability report and the print guide.
            $table->json('report')->nullable();
            $table->unsignedInteger('triangles')->nullable();
            $table->decimal('volume_mm3', 12, 3)->nullable();
            // Renamed from weight_pla_g: weight depends on the material
            // density (declared decision, docs/contracts/00 §2).
            $table->decimal('weight_g', 8, 2)->nullable();
            $table->decimal('pause_z', 6, 3)->nullable();
            $table->unsignedSmallInteger('pause_layer')->nullable();
            $table->string('printability')->nullable();
            // Human-readable message (exit 2 = engine stderr as-is; internal
            // errors get a generic message).
            $table->text('error_message')->nullable();
            // created_at drives the 24 h guest retention.
            $table->timestamps();

            $table->index('user_id');
            $table->index('guest_token');
            $table->index('status');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_tags');
    }
};
