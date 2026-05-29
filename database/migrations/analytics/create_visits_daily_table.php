<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits_daily', function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->date('date')->index();
            $table->string('panel', 40)->nullable();
            $table->string('tenant_id')->nullable();
            $table->string('tenant_type')->nullable();
            $table->char('country_code', 2)->nullable();

            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('unique_sessions')->default(0);
            $table->unsignedInteger('bot_views')->default(0);

            $table->timestamps();

            // Unique bucket key. tenant_* + country_code are nullable, MySQL allows
            // multiple NULL rows under a unique constraint — we rely on the upsert
            // logic in RollupAnalyticsCommand to coalesce nulls to a sentinel
            // string before the where-match.
            $table->unique(
                ['date', 'panel', 'tenant_id', 'tenant_type', 'country_code'],
                'visits_daily_bucket_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits_daily');
    }
};
