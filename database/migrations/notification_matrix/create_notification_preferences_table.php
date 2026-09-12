<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-loaded via loadMigrationsFrom(). One row per (user, trigger, channel)
 * that the user has explicitly toggled AWAY from the trigger's registered
 * default — an absent row means "use the default". See
 * Codenzia\FilamentPanelBase\NotificationMatrix\NotificationPreferences.
 *
 * The table name is not ours alone: on the Codenzia platform stack
 * `codenzia/notification-module` already owns a `notification_preferences`
 * table with a different schema, so an unconditional create() aborted
 * `migrate` on every host that has it ("table already exists") and pinned
 * those apps to an older release. Both directions therefore defer to
 * whatever is already there: up() skips when the table exists, and down()
 * only drops a table carrying this package's own columns, so rolling the
 * package back never destroys another package's data.
 */
return new class extends Migration
{
    /** @var array<int, string> Columns that identify this package's schema. */
    private const OWN_COLUMNS = ['user_id', 'user_type', 'trigger_key', 'channel', 'enabled'];

    public function up(): void
    {
        if (Schema::hasTable('notification_preferences')) {
            return;
        }

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_type', 191);
            $table->string('trigger_key', 191);
            $table->string('channel', 40);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'user_type']);
            $table->unique(['user_id', 'user_type', 'trigger_key', 'channel'], 'notification_preferences_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notification_preferences')) {
            return;
        }

        if (! Schema::hasColumns('notification_preferences', self::OWN_COLUMNS)) {
            return;
        }

        Schema::drop('notification_preferences');
    }
};
