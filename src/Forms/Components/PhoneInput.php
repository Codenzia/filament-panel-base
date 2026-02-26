<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Forms\Components;

use Closure;
use Filament\Forms\Components\Concerns\CanBeReadOnly;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;

/**
 * Phone input with country code dropdown and flag icons.
 *
 * Renders a two-part field: a country code select (with flag + dial code)
 * and a text input for the phone number. Stores the combined value as a
 * single string (e.g. "+962501234567").
 *
 * Usage:
 *   PhoneInput::make('phone')
 *       ->countries(fn () => Country::published()->whereNotNull('phone_code')->get())
 *
 *   PhoneInput::make('whatsapp')
 *       ->countries([
 *           ['code' => 'jo', 'phone_code' => '+962', 'name' => 'Jordan'],
 *           ['code' => 'sa', 'phone_code' => '+966', 'name' => 'Saudi Arabia'],
 *       ])
 *       ->defaultCountryCode('+962')
 */
class PhoneInput extends Field
{
    use CanBeReadOnly;
    use HasPlaceholder;

    protected string $view = 'panel-base::forms.components.filament-phone-input';

    /** @var array|Closure */
    protected array | Closure $countries = [];

    protected string | Closure $defaultCountryCode = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (PhoneInput $component, $state): void {
            // Ensure state is a string so Alpine can work with it.
            if ($state === null) {
                $component->state('');
            }
        });
    }

    /**
     * Set available countries.
     *
     * Accepts an array of associative arrays with 'code', 'phone_code', 'name' keys,
     * or an Eloquent Collection of models with those attributes.
     * Also accepts a Closure that returns either format.
     */
    public function countries(array | Closure $countries): static
    {
        $this->countries = $countries;

        return $this;
    }

    /**
     * @return array<int, array{code: string, phone_code: string, name: string}>
     */
    public function getCountries(): array
    {
        $raw = $this->evaluate($this->countries);

        // Handle Eloquent Collection
        if ($raw instanceof \Illuminate\Support\Enumerable) {
            return $raw->map(fn ($model): array => [
                'code' => strtolower((string) $model->code),
                'phone_code' => (string) $model->phone_code,
                'name' => (string) $model->name,
            ])->values()->toArray();
        }

        // Normalize plain arrays
        return collect($raw)->map(fn (array $item): array => [
            'code' => strtolower($item['code']),
            'phone_code' => $item['phone_code'],
            'name' => $item['name'],
        ])->values()->toArray();
    }

    /**
     * Set the default country code (e.g. '+962') used when no value is loaded.
     */
    public function defaultCountryCode(string | Closure $code): static
    {
        $this->defaultCountryCode = $code;

        return $this;
    }

    public function getDefaultCountryCode(): string
    {
        return (string) $this->evaluate($this->defaultCountryCode);
    }
}
