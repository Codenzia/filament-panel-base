<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Validation;

use App\Models\User;
use Codenzia\FilamentPanelBase\Auth\Rules\AllowedEmailDomain;
use Codenzia\FilamentPanelBase\Auth\Rules\NotDisposableEmail;
use Codenzia\FilamentPanelBase\Auth\Rules\ValidPhoneFormat;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;

/**
 * Builds the validation rule set for the Livewire Register component. The
 * rules vary based on AuthenticationSettings::credentials_mode (email,
 * phone, or both) so the same component covers every consumer.
 */
class RegistrationRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function build(?AuthenticationSettings $settings = null): array
    {
        $settings ??= app(AuthenticationSettings::class);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        $rules['email'] = self::emailRules($settings);
        $rules['phone'] = self::phoneRules($settings);

        return $rules;
    }

    /**
     * @return array<int, mixed>
     */
    private static function emailRules(AuthenticationSettings $settings): array
    {
        $table = self::userTable();
        $base = ['string', 'email:rfc', 'max:255', "unique:{$table},email", new NotDisposableEmail, new AllowedEmailDomain];

        return match ($settings->credentials_mode) {
            'phone' => array_merge(['nullable'], $base),
            'both' => array_merge(['required'], $base),
            default => array_merge(['required'], $base),
        };
    }

    /**
     * @return array<int, mixed>
     */
    private static function phoneRules(AuthenticationSettings $settings): array
    {
        $table = self::userTable();
        $base = ['string', 'max:20', "unique:{$table},phone", new ValidPhoneFormat];

        return match (true) {
            $settings->credentials_mode === 'phone' => array_merge(['required'], $base),
            $settings->credentials_mode === 'both' && $settings->phone_required => array_merge(['required'], $base),
            default => array_merge(['nullable'], $base),
        };
    }

    private static function userTable(): string
    {
        $model = config('filament-panel-base.user_model', User::class);

        return is_string($model) && class_exists($model) ? (new $model)->getTable() : 'users';
    }
}
