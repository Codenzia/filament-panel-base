<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Filament\Pages;

use Codenzia\FilamentPanelBase\Livewire\Demo\DemoPage;
use Codenzia\FilamentPanelBase\Models\DemoSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

/**
 * Admin settings page for the /demo gate password.
 *
 * Lets an admin view, rotate, and copy the share link without touching .env.
 * Password storage: DB-first (encrypted cast) with .env APP_DEMO_PAGE_PWD
 * as fallback — see {@see DemoPage::expectedPassword()}.
 *
 * Access defaults to the role named in
 * config('filament-panel-base.admin_role') — typically super_admin.
 * Host apps can subclass and override {@see static::canAccess()} for finer
 * control (e.g., filament-shield, custom abilities).
 */
class ManageDemoSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament-panel-base::filament.pages.manage-demo-settings';

    public bool $reveal = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        $role = (string) config('filament-panel-base.admin_role', 'super_admin');
        if (method_exists($user, 'hasRole')) {
            try {
                return (bool) $user->hasRole($role);
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }

    public static function getNavigationLabel(): string
    {
        return __('Demo Settings');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Demo Settings');
    }

    public function getHeading(): string|Htmlable
    {
        return __('Demo Settings');
    }

    public function getSubheading(): ?string
    {
        return __('Manage the password that gates the public /demo page.');
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')
                ->label(__('Regenerate Password'))
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading(__('Regenerate demo password?'))
                ->modalDescription(__('Anyone currently using the old password will need the new one. The previous value is unrecoverable.'))
                ->action(function (): void {
                    $row = DemoSetting::current();
                    $row->password = Str::random(16);
                    $row->rotated_at = now();
                    $row->save();

                    $this->reveal = true;

                    Notification::make()
                        ->title(__('New demo password generated.'))
                        ->success()
                        ->send();
                }),

            Action::make('reveal')
                ->label(fn (): string => $this->reveal ? __('Hide password') : __('Reveal password'))
                ->icon(fn (): string => $this->reveal ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                ->color('gray')
                ->action(function (): void {
                    $this->reveal = ! $this->reveal;
                }),

            Action::make('copy_link')
                ->label(__('Copy share link'))
                ->icon('heroicon-o-clipboard-document')
                ->color('gray')
                ->action(function (): void {
                    $link = $this->shareLink();
                    if ($link === null) {
                        Notification::make()
                            ->title(__('No password is set — generate one first.'))
                            ->warning()
                            ->send();

                        return;
                    }

                    $this->js(<<<JS
                        navigator.clipboard.writeText({$this->jsString($link)});
                    JS);

                    Notification::make()
                        ->title(__('Share link copied to clipboard.'))
                        ->body($link)
                        ->success()
                        ->send();
                }),
        ];
    }

    public function currentPassword(): ?string
    {
        $row = DemoSetting::current();
        if (is_string($row->password) && $row->password !== '') {
            return $row->password;
        }

        $env = (string) config('filament-panel-base.demo.password_env', 'APP_DEMO_PAGE_PWD');
        $value = env($env);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function passwordSource(): string
    {
        $row = DemoSetting::current();
        if (is_string($row->password) && $row->password !== '') {
            return 'database';
        }
        $env = (string) config('filament-panel-base.demo.password_env', 'APP_DEMO_PAGE_PWD');
        $value = env($env);
        if (is_string($value) && $value !== '') {
            return 'env';
        }

        return 'unset';
    }

    public function demoUrl(): string
    {
        $route = (string) config('filament-panel-base.demo.route', '/demo');

        return rtrim(config('app.url', ''), '/').'/'.ltrim($route, '/');
    }

    public function shareLink(): ?string
    {
        $pwd = $this->currentPassword();
        if ($pwd === null) {
            return null;
        }

        return $this->demoUrl().' — '.__('password').': '.$pwd;
    }

    public function metadata(): array
    {
        $row = DemoSetting::current();

        return [
            'rotated_at' => $row->rotated_at,
            'last_used_at' => $row->last_used_at,
        ];
    }

    private function jsString(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
