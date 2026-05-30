<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Sessions\Listeners;

use Codenzia\FilamentPanelBase\Sessions\Events\NewDeviceLogin;
use Codenzia\FilamentPanelBase\Sessions\Services\DeviceSessionRepository;
use Codenzia\FilamentPanelBase\Sessions\Settings\SessionManagementSettings;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Listens to Illuminate Login events. Before this login wrote a row to
 * the `sessions` table, was there any previous session for this user
 * whose UA fingerprint matches the current request's? If not, fire
 * NewDeviceLogin so hosts can notify the user.
 *
 * Cheap by design: short-circuits when settings disable the feature,
 * when the session driver isn't database, or when the sessions table
 * doesn't exist yet (fresh install).
 */
class DetectNewDeviceLogin
{
    public function __construct(
        private SessionManagementSettings $settings,
        private DeviceSessionRepository $repo,
    ) {}

    public function handle(Login $event): void
    {
        try {
            if (! $this->settings->enabled || ! $this->settings->notify_on_new_device) {
                return;
            }
        } catch (\Throwable) {
            // Settings table missing on fresh install — silently skip.
            return;
        }

        if (! $this->repo->driverIsDatabase()) {
            return;
        }

        $table = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            return;
        }

        $ip = (string) (request()?->ip() ?? '');
        $ua = (string) (request()?->userAgent() ?? '');

        if ($ip === '' && $ua === '') {
            return;
        }

        $fingerprint = $this->fingerprint($ip, $ua);
        $userId = $event->user->getAuthIdentifier();

        try {
            $existing = DB::table($table)
                ->where('user_id', $userId)
                ->get(['ip_address', 'user_agent'])
                ->contains(fn ($row) => $this->fingerprint(
                    (string) ($row->ip_address ?? ''),
                    (string) ($row->user_agent ?? ''),
                ) === $fingerprint);
        } catch (\Throwable) {
            return;
        }

        if ($existing) {
            return;
        }

        event(new NewDeviceLogin($event->user, $ip ?: null, $ua ?: null));
    }

    private function fingerprint(string $ip, string $ua): string
    {
        return hash('sha256', $ip.'|'.$ua);
    }
}
