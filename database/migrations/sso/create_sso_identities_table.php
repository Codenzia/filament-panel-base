<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-loaded via loadMigrationsFrom(). Links a local user to the subject
 * identifier a given OIDC provider issued for them, so a returning sign-in
 * resolves by (provider, subject) instead of re-matching on the email claim
 * — an address can be reassigned inside a tenant, the subject cannot.
 *
 * The identity is keyed by (provider, issuer, subject): the subject is only
 * unique within the issuer that minted it, so an operator re-pointing a
 * provider entry at a different IdP cannot have a colliding subject resolve to
 * the previous issuer's user.
 *
 * `issuer` and `subject` are capped at 191 characters so the composite unique
 * index fits inside MySQL's 3072-byte utf8mb4 index limit alongside `provider`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sso_identities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('provider', 64);
            $table->string('issuer', 191);
            $table->string('subject', 191);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'issuer', 'subject']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sso_identities');
    }
};
