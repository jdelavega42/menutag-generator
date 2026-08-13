<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded logos, owned by a user OR a guest token — xor enforced by the
 * application (contract 01). Guest logos are removed when the job completes
 * or by the retention command.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logo_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->uuid('guest_token')->nullable();
            // Relative to the 'assets' disk root — never absolute: app and
            // worker are different containers (contract 01).
            $table->string('disk_path');
            // Original name is display-only; the on-disk name is generated
            // by the server (WS-5).
            $table->string('original_name');
            $table->string('mime');
            $table->unsignedInteger('size_bytes');
            $table->timestamps();

            $table->index('user_id');
            $table->index('guest_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logo_assets');
    }
};
