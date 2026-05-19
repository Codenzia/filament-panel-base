<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Filament\Pages;

use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Admin settings page for the entire Auth module. Surfaces every
 * {@see AuthenticationSettings} field grouped into sections — registration,
 * verification, OTP, social login, and throttling — so admins don't need
 * to touch the database directly.
 *
 * Opt-in via the plugin:
 *
 *     FilamentPanelBasePlugin::make()->withFilamentAuthSettingsPage()
 *
 * --- Authorisation (REQUIRED) ---
 *
 * This page is security-sensitive: it controls authentication policy for
 * the whole app. The default `canAccess()` therefore returns `false` —
 * registering the page on a panel does NOT expose it. Hosts MUST opt in
 * to expose the page by subclassing and providing their own check:
 *
 *  - With `bezhansalleh/filament-shield`:
 *
 *      class AuthSettings extends \Codenzia\FilamentPanelBase\Auth\Filament\Pages\ManageAuthenticationSettings
 *      {
 *          use \BezhanSalleh\FilamentShield\Traits\HasPageShield;
 *      }
 *
 *  - With a simple role/ability check:
 *
 *      class AuthSettings extends \Codenzia\FilamentPanelBase\Auth\Filament\Pages\ManageAuthenticationSettings
 *      {
 *          public static function canAccess(): bool
 *          {
 *              return auth()->user()?->can('manage-auth-settings') ?? false;
 *          }
 *      }
 *
 * Then hand the subclass to the plugin:
 *
 *     FilamentPanelBasePlugin::make()
 *         ->withFilamentAuthSettingsPage(\App\Filament\Pages\AuthSettings::class);
 */
class ManageAuthenticationSettings extends Page implements HasForms
{
    use InteractsWithForms;

    /**
     * Fail-closed default: the base page is never accessible without an
     * explicit host-side override. See the class docblock for the
     * subclass patterns (filament-shield, Gate ability, etc.).
     */
    public static function canAccess(): bool
    {
        return false;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament-panel-base::filament.auth.manage-authentication-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('filament-panel-base::auth.settings_nav_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-panel-base::auth.settings_nav_group');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('filament-panel-base::auth.settings_title');
    }

    public function mount(): void
    {
        $settings = app(AuthenticationSettings::class);

        $this->form->fill([
            'registration_mode' => $settings->registration_mode,
            'disposable_email_blocking' => $settings->disposable_email_blocking,
            'require_email_verification' => $settings->require_email_verification,
            'require_phone_verification' => $settings->require_phone_verification,
            'credentials_mode' => $settings->credentials_mode,
            'phone_required' => $settings->phone_required,
            'default_country_code' => $settings->default_country_code,
            'otp_driver' => $settings->otp_driver,
            'allowed_otp_drivers' => $settings->allowed_otp_drivers,
            'otp_code_length' => $settings->otp_code_length,
            'otp_ttl_minutes' => $settings->otp_ttl_minutes,
            'social_providers_enabled' => $settings->social_providers_enabled,
            'social_email_linking' => $settings->social_email_linking,
            'social_trust_verified_email' => $settings->social_trust_verified_email,
            'throttle_per_minute' => $settings->throttle_per_minute,
            'throttle_per_day' => $settings->throttle_per_day,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament-panel-base::auth.settings_section_registration'))
                    ->description(__('filament-panel-base::auth.settings_section_registration_description'))
                    ->icon('heroicon-o-user-plus')
                    ->components([
                        Select::make('registration_mode')
                            ->label(__('filament-panel-base::auth.settings_registration_mode'))
                            ->options([
                                'open' => __('filament-panel-base::auth.settings_registration_mode_open'),
                                'moderated' => __('filament-panel-base::auth.settings_registration_mode_moderated'),
                            ])
                            ->helperText(__('filament-panel-base::auth.settings_registration_mode_help'))
                            ->required(),
                        Select::make('credentials_mode')
                            ->label(__('filament-panel-base::auth.settings_credentials_mode'))
                            ->options([
                                'email' => __('filament-panel-base::auth.settings_credentials_mode_email'),
                                'phone' => __('filament-panel-base::auth.settings_credentials_mode_phone'),
                                'both' => __('filament-panel-base::auth.settings_credentials_mode_both'),
                            ])
                            ->helperText(__('filament-panel-base::auth.settings_credentials_mode_help'))
                            ->required(),
                        Toggle::make('phone_required')
                            ->label(__('filament-panel-base::auth.settings_phone_required'))
                            ->helperText(__('filament-panel-base::auth.settings_phone_required_help')),
                        TextInput::make('default_country_code')
                            ->label(__('filament-panel-base::auth.settings_default_country_code'))
                            ->placeholder('+1')
                            ->maxLength(6)
                            ->required(),
                        Toggle::make('disposable_email_blocking')
                            ->label(__('filament-panel-base::auth.settings_disposable_email_blocking'))
                            ->helperText(__('filament-panel-base::auth.settings_disposable_email_blocking_help')),
                    ])
                    ->columns(2),

                Section::make(__('filament-panel-base::auth.settings_section_verification'))
                    ->description(__('filament-panel-base::auth.settings_section_verification_description'))
                    ->icon('heroicon-o-envelope-open')
                    ->components([
                        Toggle::make('require_email_verification')
                            ->label(__('filament-panel-base::auth.settings_require_email_verification')),
                        Toggle::make('require_phone_verification')
                            ->label(__('filament-panel-base::auth.settings_require_phone_verification')),
                    ])
                    ->columns(2),

                Section::make(__('filament-panel-base::auth.settings_section_otp'))
                    ->description(__('filament-panel-base::auth.settings_section_otp_description'))
                    ->icon('heroicon-o-key')
                    ->components([
                        Select::make('otp_driver')
                            ->label(__('filament-panel-base::auth.settings_otp_driver'))
                            ->options(fn (callable $get): array => collect($get('allowed_otp_drivers') ?? ['email'])
                                ->mapWithKeys(fn (string $driver): array => [
                                    $driver => __('filament-panel-base::auth.channel.'.$driver),
                                ])
                                ->all())
                            ->required(),
                        TagsInput::make('allowed_otp_drivers')
                            ->label(__('filament-panel-base::auth.settings_allowed_otp_drivers'))
                            ->helperText(__('filament-panel-base::auth.settings_allowed_otp_drivers_help'))
                            ->suggestions(['email', 'whatsapp', 'twilio', 'vonage', 'null']),
                        TextInput::make('otp_code_length')
                            ->label(__('filament-panel-base::auth.settings_otp_code_length'))
                            ->numeric()
                            ->minValue(4)
                            ->maxValue(10)
                            ->required(),
                        TextInput::make('otp_ttl_minutes')
                            ->label(__('filament-panel-base::auth.settings_otp_ttl_minutes'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make(__('filament-panel-base::auth.settings_section_social'))
                    ->description(__('filament-panel-base::auth.settings_section_social_description'))
                    ->icon('heroicon-o-globe-alt')
                    ->components([
                        TagsInput::make('social_providers_enabled')
                            ->label(__('filament-panel-base::auth.settings_social_providers_enabled'))
                            ->helperText(__('filament-panel-base::auth.settings_social_providers_enabled_help'))
                            ->suggestions(['google', 'github', 'facebook', 'apple', 'microsoft', 'twitter', 'linkedin', 'gitlab'])
                            ->columnSpanFull(),
                        Select::make('social_email_linking')
                            ->label(__('filament-panel-base::auth.settings_social_email_linking'))
                            ->options([
                                'require_login' => __('filament-panel-base::auth.settings_social_email_linking_require_login'),
                                'trust_verified' => __('filament-panel-base::auth.settings_social_email_linking_trust_verified'),
                                'auto' => __('filament-panel-base::auth.settings_social_email_linking_auto'),
                            ])
                            ->helperText(__('filament-panel-base::auth.settings_social_email_linking_help'))
                            ->required(),
                        Toggle::make('social_trust_verified_email')
                            ->label(__('filament-panel-base::auth.settings_social_trust_verified_email'))
                            ->helperText(__('filament-panel-base::auth.settings_social_trust_verified_email_help')),
                    ])
                    ->columns(2),

                Section::make(__('filament-panel-base::auth.settings_section_throttle'))
                    ->description(__('filament-panel-base::auth.settings_section_throttle_description'))
                    ->icon('heroicon-o-shield-exclamation')
                    ->components([
                        TextInput::make('throttle_per_minute')
                            ->label(__('filament-panel-base::auth.settings_throttle_per_minute'))
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        TextInput::make('throttle_per_day')
                            ->label(__('filament-panel-base::auth.settings_throttle_per_day'))
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $settings = app(AuthenticationSettings::class);

        $settings->registration_mode = $data['registration_mode'];
        $settings->disposable_email_blocking = (bool) $data['disposable_email_blocking'];
        $settings->require_email_verification = (bool) $data['require_email_verification'];
        $settings->require_phone_verification = (bool) $data['require_phone_verification'];
        $settings->credentials_mode = $data['credentials_mode'];
        $settings->phone_required = (bool) $data['phone_required'];
        $settings->default_country_code = $data['default_country_code'];
        $settings->otp_driver = $data['otp_driver'];
        $settings->allowed_otp_drivers = array_values(array_unique($data['allowed_otp_drivers'] ?? []));
        $settings->otp_code_length = (int) $data['otp_code_length'];
        $settings->otp_ttl_minutes = (int) $data['otp_ttl_minutes'];
        $settings->social_providers_enabled = array_values(array_unique($data['social_providers_enabled'] ?? []));
        $settings->social_email_linking = $data['social_email_linking'];
        $settings->social_trust_verified_email = (bool) $data['social_trust_verified_email'];
        $settings->throttle_per_minute = (int) $data['throttle_per_minute'];
        $settings->throttle_per_day = (int) $data['throttle_per_day'];

        $settings->save();

        Notification::make()
            ->title(__('filament-panel-base::auth.settings_saved'))
            ->success()
            ->send();
    }
}
