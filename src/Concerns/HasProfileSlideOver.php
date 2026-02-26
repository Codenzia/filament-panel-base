<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Adds a profile-editing slideOver action to the Filament user menu.
 *
 * Override the protected methods in child providers to customise
 * form fields, data hydration, or save logic per panel.
 */
trait HasProfileSlideOver
{
    /**
     * Build the profile slideOver action for the user menu.
     */
    protected function getProfileSlideOverAction(): Action
    {
        return Action::make('edit-profile')
            ->label(__('Edit Profile'))
            ->icon('heroicon-o-user')
            ->slideOver()
            ->modalWidth('2xl')
            ->fillForm(fn (): array => $this->getProfileFormData())
            ->form(fn (): array => [
                Tabs::make('Profile')
                    ->vertical()
                    ->tabs($this->getProfileFormTabs())
                    ->columnSpanFull(),
            ])
            ->action(fn (array $data) => $this->saveProfileData($data))
            ->sort(-1);
    }

    /**
     * Get the data to fill the profile form.
     * Override to add relationship data (e.g. avatar media IDs).
     */
    protected function getProfileFormData(): array
    {
        return filament()->auth()->user()->attributesToArray();
    }

    /**
     * Get the tabs for the profile form.
     */
    protected function getProfileFormTabs(): array
    {
        return [
            Tab::make(__('Personal Information'))
                ->icon('heroicon-o-user')
                ->components($this->getProfilePersonalInfoComponents()),
            Tab::make(__('Change Password'))
                ->icon('heroicon-o-lock-closed')
                ->components($this->getProfilePasswordComponents()),
        ];
    }

    /**
     * Get the personal information form components.
     * Override in child providers to add fields like phone, avatar, etc.
     */
    protected function getProfilePersonalInfoComponents(): array
    {
        return [
            TextInput::make('name')
                ->label(__('filament-panels::auth/pages/edit-profile.form.name.label'))
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('filament-panels::auth/pages/edit-profile.form.email.label'))
                ->email()
                ->required()
                ->maxLength(255)
                ->unique('users', 'email', ignorable: fn () => filament()->auth()->user()),
        ];
    }

    /**
     * Get the password form components.
     */
    protected function getProfilePasswordComponents(): array
    {
        return [
            TextInput::make('password')
                ->label(__('filament-panels::auth/pages/edit-profile.form.password.label'))
                ->password()
                ->revealable(filament()->arePasswordsRevealable())
                ->rule(Password::default())
                ->autocomplete('new-password')
                ->dehydrated(fn ($state): bool => filled($state))
                ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                ->live(debounce: 500)
                ->same('passwordConfirmation'),
            TextInput::make('passwordConfirmation')
                ->label(__('filament-panels::auth/pages/edit-profile.form.password_confirmation.label'))
                ->password()
                ->autocomplete('new-password')
                ->revealable(filament()->arePasswordsRevealable())
                ->required()
                ->visible(fn (Get $get): bool => filled($get('password')))
                ->dehydrated(false),
        ];
    }

    /**
     * Save the profile form data.
     * Override in child providers to handle relationships (e.g. media sync).
     */
    protected function saveProfileData(array $data): void
    {
        $user = filament()->auth()->user();

        if (empty($data['password'])) {
            unset($data['password']);
        }
        unset($data['passwordConfirmation']);

        $user->update($data);

        if (request()->hasSession() && isset($data['password'])) {
            request()->session()->put([
                'password_hash_' . filament()->getAuthGuard() => $data['password'],
            ]);
        }

        Notification::make()
            ->success()
            ->title(__('filament-panels::auth/pages/edit-profile.notifications.saved.title'))
            ->send();
    }
}
