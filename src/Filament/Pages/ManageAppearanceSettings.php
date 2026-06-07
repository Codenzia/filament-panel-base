<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Filament\Pages;

use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
use Codenzia\FilamentPanelBase\Support\ThemePresets;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Shared admin "Appearance" settings page — opt in with
 * FilamentPanelBasePlugin::make()->withAppearanceSettings(). Edits the panel's
 * branding (app name, tagline, logo, favicon, theme preset + colors) live,
 * instead of editing the settings row by hand / in code.
 *
 * It operates on the panel's *own* settings instance (whatever the host wired via
 * ->settingsUsing() / ->settingsClass()), reading/writing only the fields that
 * exist on it — so an app whose settings omits, say, a tagline simply won't show
 * that field. Every field maps to the same properties BasePanelProvider already
 * reads to brand the panel, so this is a UI over the existing convention.
 */
class ManageAppearanceSettings extends Page implements HasForms
{
    use InteractsWithForms;

    /** Host-supplied access gate (set by withAppearanceSettings). */
    public static ?\Closure $authorizeUsing = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';

    protected string $view = 'filament-panel-base::filament.pages.manage-appearance-settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /** The standard branding fields this page manages (only those present on the settings are shown). */
    private const FIELDS = ['app_name', 'app_tagline', 'logo_url', 'favicon_url', 'theme_preset', 'primary_color', 'secondary_color'];

    public static function canAccess(): bool
    {
        if (static::$authorizeUsing !== null) {
            return (bool) (static::$authorizeUsing)();
        }

        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return method_exists($user, 'isSuperAdmin') ? (bool) $user->isSuperAdmin() : true;
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('filament-panel-base.appearance.navigation_group', 'Settings'));
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-panel-base.appearance.navigation_sort', 90);
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return config('filament-panel-base.appearance.navigation_icon', 'heroicon-o-paint-brush');
    }

    public static function getNavigationLabel(): string
    {
        return __('Appearance');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Appearance');
    }

    public function getSubheading(): ?string
    {
        return __('Branding for this panel — name, logo, and theme colors. Changes apply on the next page load.');
    }

    /** The panel's configured settings instance (host-defined), or null if none wired. */
    protected function settings(): ?object
    {
        return FilamentPanelBasePlugin::make()->resolveSettings();
    }

    public function mount(): void
    {
        $settings = $this->settings();
        if ($settings === null) {
            return;
        }

        $fill = [];
        foreach (self::FIELDS as $field) {
            if (property_exists($settings, $field)) {
                $fill[$field] = $settings->{$field};
            }
        }
        $this->form->fill($fill);
    }

    public function form(Schema $schema): Schema
    {
        $settings = $this->settings();
        $has = fn (string $field): bool => $settings !== null && property_exists($settings, $field);

        $identity = array_values(array_filter([
            $has('app_name') ? TextInput::make('app_name')->label(__('App name'))->required()->maxLength(120) : null,
            $has('app_tagline') ? TextInput::make('app_tagline')->label(__('Tagline'))->maxLength(255)->columnSpanFull() : null,
            $has('logo_url') ? TextInput::make('logo_url')->label(__('Logo URL / path'))->maxLength(255)
                ->helperText(__('A public path (e.g. images/logo.png) or absolute URL.')) : null,
            $has('favicon_url') ? TextInput::make('favicon_url')->label(__('Favicon URL / path'))->maxLength(255) : null,
        ]));

        $theme = array_values(array_filter([
            $has('theme_preset') ? Select::make('theme_preset')->label(__('Theme preset'))->live()->native(false)
                ->options(array_merge(ThemePresets::labels(), ['custom' => __('Custom (choose colors)')])) : null,
            $has('primary_color') ? ColorPicker::make('primary_color')->label(__('Primary color'))
                ->visible(fn (Get $get): bool => ! $has('theme_preset') || $get('theme_preset') === 'custom') : null,
            $has('secondary_color') ? ColorPicker::make('secondary_color')->label(__('Secondary color'))
                ->visible(fn (Get $get): bool => ! $has('theme_preset') || $get('theme_preset') === 'custom') : null,
        ]));

        $sections = [];
        if ($identity !== []) {
            $sections[] = Section::make(__('Identity'))->icon('heroicon-o-identification')
                ->description(__('Name, tagline and imagery shown across the panel.'))
                ->components($identity)->columns(2);
        }
        if ($theme !== []) {
            $sections[] = Section::make(__('Theme'))->icon('heroicon-o-swatch')
                ->description(__('Pick a preset, or “Custom” to choose your own colors.'))
                ->components($theme)->columns(2);
        }

        return $schema->components($sections)->statePath('data');
    }

    public function save(): void
    {
        $settings = $this->settings();
        if ($settings === null) {
            Notification::make()->title(__('No settings are configured for this panel.'))->danger()->send();

            return;
        }

        $data = $this->form->getState();
        foreach (self::FIELDS as $field) {
            if (property_exists($settings, $field) && array_key_exists($field, $data)) {
                $settings->{$field} = $data[$field];
            }
        }
        $settings->save();

        Notification::make()->title(__('Appearance updated.'))
            ->body(__('Reload to see the new branding applied.'))->success()->send();
    }
}
