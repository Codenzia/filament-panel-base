<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_events', function (Blueprint $table): void {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('tenant_id')->nullable();
            $table->string('tenant_type')->nullable();
            $table->string('panel', 40)->nullable();

            // login.success | login.failed | logout | register
            // | otp.requested | otp.verified | social.login
            // | moderation.suspended | moderation.approved | moderation.pending
            // | password.reset
            $table->string('type', 40)->index();
            $table->string('channel', 32)->nullable();

            $table->char('ip_hash', 64)->nullable();
            $table->char('country_code', 2)->nullable();

            $table->json('meta')->nullable();

            $table->timestamp('created_at')->index();

            $table->index(['type', 'created_at']);
            $table->index(['tenant_type', 'tenant_id', 'created_at'], 'auth_events_tenant_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_events');
    }
};
