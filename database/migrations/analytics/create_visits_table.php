<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->string('session_id', 40)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('tenant_id')->nullable();
            $table->string('tenant_type')->nullable();

            $table->string('panel', 40)->nullable();
            $table->string('route_name', 160)->nullable();
            $table->string('path', 2048);
            $table->char('method', 8);
            $table->unsignedSmallInteger('status');

            $table->string('referrer_host', 255)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->char('ip_hash', 64)->nullable();

            $table->string('device_type', 20)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('platform', 40)->nullable();
            $table->boolean('is_bot')->default(false);

            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('created_at')->index();

            $table->index(['panel', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['tenant_type', 'tenant_id', 'created_at'], 'visits_tenant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
