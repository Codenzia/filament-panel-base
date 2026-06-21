<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a server-side nonce used to revoke "remember this device, skip 2FA"
 * cookies. The remember-device cookie is an HMAC that mixes this token in,
 * so rotating it (on "log out everywhere" / device revoke / disabling 2FA)
 * invalidates every outstanding remember-device cookie for the user without
 * a per-cookie lookup table. Auto-loaded via loadMigrationsFrom().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'two_factor_remember_token')) {
                $table->string('two_factor_remember_token', 100)->nullable()->after('two_factor_confirmed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'two_factor_remember_token')) {
                $table->dropColumn('two_factor_remember_token');
            }
        });
    }
};
