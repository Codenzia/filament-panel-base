<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\NotificationMatrix\Filament\Pages;

use Codenzia\FilamentPanelBase\NotificationMatrix\Models\NotificationPreference;
use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationPreferences;
use Codenzia\FilamentPanelBase\NotificationMatrix\NotificationTriggers;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * User-level "Notification Preferences" page — opt in with
 * FilamentPanelBasePlugin::make()->withNotificationPreferencesPage(). Lets the
 * signed-in user toggle, per registered trigger and per channel, whether they
 * want that notification delivered. Every trigger the host/plugins registered
 * via {@see NotificationTriggers::register()} shows up here automatically,
 * grouped by its `group` meta.
 *
 * Only In-app (database) and Email (mail) columns are rendered — the two
 * channels the fleet's Notification classes actually vary delivery on. A
 * trigger that only declared one of them shows a dash in the other column.
 *
 * Toggling writes/deletes a single {@see NotificationPreference} row: when the
 * new value matches the trigger's default, the override row is deleted
 * instead of stored, keeping the table limited to actual opt-outs/opt-ins.
 */
class ManageNotificationPreferences extends Page
{
    /** Host-supplied access gate (set by withNotificationPreferencesPage). */
    public static ?\Closure $authorizeUsing = null;

    /** @var array<int, string> Channels this page renders toggles for. */
    protected const CHANNELS = ['database', 'mail'];

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell-alert';

    protected string $view = 'filament-panel-base::filament.pages.manage-notification-preferences';

    public string $search = '';

    public static function canAccess(): bool
    {
        if (! NotificationPreferences::storageAvailable()) {
            return false;
        }

        if (static::$authorizeUsing !== null) {
            return (bool) (static::$authorizeUsing)();
        }

        return auth()->check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-panel-base.notification-matrix.enabled', false) && static::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('filament-panel-base.notification-matrix.navigation_group', 'Settings'));
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-panel-base.notification-matrix.navigation_sort', 95);
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return config('filament-panel-base.notification-matrix.navigation_icon', 'heroicon-o-bell-alert');
    }

    public static function getNavigationLabel(): string
    {
        return __('Notification Preferences');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Notification Preferences');
    }

    public function getSubheading(): ?string
    {
        return __('Choose which notifications reach you, and how.');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset_to_defaults')
                ->label(__('Reset to defaults'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading(__('Reset notification preferences?'))
                ->modalDescription(__('Every toggle you have changed reverts to its default. This cannot be undone.'))
                ->action('resetToDefaults'),
        ];
    }

    /**
     * Every registered trigger, grouped by `group` meta, filtered by the
     * current search term (matches label or key, case-insensitively).
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function getGroupedTriggers(): array
    {
        $term = mb_strtolower(trim($this->search));
        $groups = [];

        foreach (NotificationTriggers::all() as $key => $meta) {
            if ($term !== ''
                && ! str_contains(mb_strtolower((string) $meta['label']), $term)
                && ! str_contains(mb_strtolower($key), $term)) {
                continue;
            }

            $groups[(string) $meta['group']][$key] = $meta;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * @return array<int, string>
     */
    public function getChannels(): array
    {
        return self::CHANNELS;
    }

    public function channelLabel(string $channel): string
    {
        return match ($channel) {
            'database' => __('In-app'),
            'mail' => __('Email'),
            default => ucfirst($channel),
        };
    }

    public function isChannelEnabled(string $triggerKey, string $channel): bool
    {
        $user = $this->user();

        if ($user === null) {
            return true;
        }

        return NotificationPreferences::allows($user, $triggerKey, $channel);
    }

    public function toggleChannel(string $triggerKey, string $channel): void
    {
        $user = $this->user();
        abort_unless($user !== null, 403);

        $meta = NotificationTriggers::get($triggerKey);
        abort_if($meta === null, 404);

        $default = (bool) ($meta['default_enabled'] ?? true);
        $next = ! $this->isChannelEnabled($triggerKey, $channel);

        $attributes = [
            'user_id' => $user->getKey(),
            'user_type' => $user->getMorphClass(),
            'trigger_key' => $triggerKey,
            'channel' => $channel,
        ];

        if ($next === $default) {
            // Matches the registered default again — delete the override
            // row rather than storing a redundant one.
            NotificationPreference::query()->where($attributes)->delete();

            return;
        }

        NotificationPreference::query()->updateOrCreate($attributes, ['enabled' => $next]);
    }

    public function resetToDefaults(): void
    {
        $user = $this->user();
        abort_unless($user !== null, 403);

        NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->where('user_type', $user->getMorphClass())
            ->delete();

        Notification::make()
            ->title(__('Notification preferences reset to defaults.'))
            ->success()
            ->send();
    }

    protected function user(): ?Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }
}
