<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Concerns;

use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorAuthenticator;
use Codenzia\FilamentPanelBase\TwoFactor\Services\TwoFactorChallengeSession;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View as SchemaView;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds a "Two-Factor Authentication" tab to the profile slide-over. The host
 * PanelProvider already mixes in HasProfileSlideOver; this trait piggybacks
 * by overriding getProfileFormTabs() and getProfileFormData().
 *
 *     use HasProfileSlideOver, HasTwoFactorProfileTab;
 *
 *     protected function getProfileFormTabs(): array
 *     {
 *         return [
 *             ...parent::getProfileFormTabs(),
 *             $this->getTwoFactorProfileTab(),
 *         ];
 *     }
 *
 * The tab renders one of three states depending on the user's enrolment:
 *  - Not enrolled         → "Enable" button (provisions a fresh secret).
 *  - Pending confirmation → QR code + code input.
 *  - Confirmed            → "Disable" + "Regenerate recovery codes" actions.
 */
trait HasTwoFactorProfileTab
{
    protected function getTwoFactorProfileTab(): Tab
    {
        return Tab::make(__('filament-panel-base::two-factor.tab_label'))
            ->icon('heroicon-o-shield-check')
            ->components([
                SchemaView::make('filament-panel-base::filament.two-factor.profile-tab')
                    ->viewData(fn (): array => $this->buildTwoFactorTabViewData())
                    ->columnSpanFull(),
                TextInput::make('two_factor_code')
                    ->label(__('filament-panel-base::two-factor.confirmation_code_label'))
                    ->placeholder('123 456')
                    ->maxLength(8)
                    ->dehydrated(false)
                    ->visible(fn (Get $get): bool => filled($get('two_factor_pending'))),
            ])
            ->headerActions([
                Action::make('enable-two-factor')
                    ->label(__('filament-panel-base::two-factor.enable_button'))
                    ->icon('heroicon-o-key')
                    ->color('primary')
                    ->visible(fn (): bool => ! $this->currentUserHasTwoFactor() && ! $this->currentUserHasTwoFactorPending())
                    ->requiresConfirmation()
                    ->modalDescription(__('filament-panel-base::two-factor.enable_description'))
                    ->action(fn () => $this->enableTwoFactor()),

                Action::make('confirm-two-factor')
                    ->label(__('filament-panel-base::two-factor.confirm_button'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (): bool => $this->currentUserHasTwoFactorPending())
                    ->schema([
                        TextInput::make('code')
                            ->label(__('filament-panel-base::two-factor.confirmation_code_label'))
                            ->required()
                            ->maxLength(8),
                    ])
                    ->action(fn (array $data) => $this->confirmTwoFactor($data['code'] ?? '')),

                Action::make('regenerate-recovery-codes')
                    ->label(__('filament-panel-base::two-factor.regenerate_button'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (): bool => $this->currentUserHasTwoFactor())
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('filament-panel-base::two-factor.current_password_label'))
                            ->password()
                            ->required()
                            ->currentPassword(),
                    ])
                    ->action(fn () => $this->regenerateRecoveryCodes()),

                Action::make('disable-two-factor')
                    ->label(__('filament-panel-base::two-factor.disable_button'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (): bool => $this->currentUserHasTwoFactor() || $this->currentUserHasTwoFactorPending())
                    ->modalDescription(__('filament-panel-base::two-factor.disable_description'))
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('filament-panel-base::two-factor.current_password_label'))
                            ->password()
                            ->required()
                            ->currentPassword(),
                    ])
                    ->action(fn () => $this->disableTwoFactor()),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildTwoFactorTabViewData(): array
    {
        $user = filament()->auth()->user();

        if (! $user instanceof Model || ! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)) {
            return [
                'state' => 'unavailable',
            ];
        }

        if ($user->hasTwoFactorEnabled()) {
            return [
                'state' => 'enabled',
                'recoveryCount' => count((array) $user->two_factor_recovery_codes),
                'recoveryCodes' => $this->pullFlashedRecoveryCodes(),
            ];
        }

        $rawSecret = $user->getRawOriginal('two_factor_secret');

        if (! empty($rawSecret)) {
            $secret = $user->two_factor_secret;
            $auth = app(TwoFactorAuthenticator::class);
            $uri = $auth->provisioningUri($secret, (string) ($user->email ?? $user->getAuthIdentifier()));

            return [
                'state' => 'pending',
                'qrSvg' => $auth->qrCodeSvg($uri),
                'manualKey' => trim(chunk_split($secret, 4, ' ')),
                'recoveryCodes' => $this->pullFlashedRecoveryCodes(),
            ];
        }

        return [
            'state' => 'disabled',
        ];
    }

    protected function currentUserHasTwoFactor(): bool
    {
        $user = filament()->auth()->user();

        return $user instanceof Model
            && in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)
            && $user->hasTwoFactorEnabled();
    }

    protected function currentUserHasTwoFactorPending(): bool
    {
        $user = filament()->auth()->user();

        if (! $user instanceof Model || ! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)) {
            return false;
        }

        return ! empty($user->getRawOriginal('two_factor_secret'))
            && ! $user->hasTwoFactorEnabled();
    }

    protected function enableTwoFactor(): void
    {
        $user = filament()->auth()->user();

        if (! $user instanceof Model || ! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)) {
            return;
        }

        $codes = $user->generateTwoFactorSecret();

        // Flash plaintext recovery codes to the session so they're shown
        // exactly once — refreshing the page drops them.
        session()->flash('two_factor_recovery_codes', $codes);

        Notification::make()
            ->title(__('filament-panel-base::two-factor.enable_notification'))
            ->success()
            ->send();
    }

    protected function confirmTwoFactor(string $code): void
    {
        $user = filament()->auth()->user();

        if (! $user instanceof Model || ! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)) {
            return;
        }

        if (! $user->confirmTwoFactor($code)) {
            Notification::make()
                ->title(__('filament-panel-base::two-factor.invalid_code'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('filament-panel-base::two-factor.confirmed_notification'))
            ->success()
            ->send();
    }

    protected function regenerateRecoveryCodes(): void
    {
        $user = filament()->auth()->user();

        if (! $user instanceof Model || ! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)) {
            return;
        }

        $codes = $user->replaceRecoveryCodes();
        session()->flash('two_factor_recovery_codes', $codes);

        Notification::make()
            ->title(__('filament-panel-base::two-factor.recovery_regenerated'))
            ->success()
            ->send();
    }

    protected function disableTwoFactor(): void
    {
        $user = filament()->auth()->user();

        if (! $user instanceof Model || ! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)) {
            return;
        }

        $user->disableTwoFactor();

        try {
            app(TwoFactorChallengeSession::class)->forgetDevice();
        } catch (\Throwable) {
            // Cookie clearing is best-effort.
        }

        Notification::make()
            ->title(__('filament-panel-base::two-factor.disabled_notification'))
            ->success()
            ->send();
    }

    /**
     * @return array<int, string>
     */
    private function pullFlashedRecoveryCodes(): array
    {
        $codes = session('two_factor_recovery_codes', []);

        return is_array($codes) ? array_values($codes) : [];
    }
}
