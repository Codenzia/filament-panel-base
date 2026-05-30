<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-loaded via loadMigrationsFrom(). Tracks the last N records each user
 * opened in the admin panel so the command palette can show them under a
 * "Recent" header.
 *
 * Pruning is per-user, per-panel: a tiny chunk happens at write time via the
 * RecentViewRecorder so this table stays small without a scheduled job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_palette_recent_views', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('panel', 40)->nullable()->index();
            $table->string('resource_class', 255);
            $table->string('record_id', 64);
            $table->string('label', 255);
            $table->string('url', 2048);
            $table->timestamp('viewed_at')->index();
            $table->timestamps();

            $table->unique(['user_id', 'panel', 'resource_class', 'record_id'], 'cp_recent_views_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_palette_recent_views');
    }
};
