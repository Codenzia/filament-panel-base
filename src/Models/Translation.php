<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Models;

use Codenzia\FilamentPanelBase\Contracts\ProvidesLocales;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\TranslationLoader\LanguageLine;

class Translation extends LanguageLine
{
    use SoftDeletes;

    protected $table = 'language_lines';

    public array $translatable = ['text'];

    public $guarded = ['id'];

    protected $fillable = [
        'group',
        'key',
        'text',
        'namespace',
    ];

    protected $casts = [
        'text' => 'array',
    ];

    /**
     * Get active locale codes from the ProvidesLocales provider or config fallback.
     *
     * @return array<string>
     */
    public static function getLocales(): array
    {
        $providerClass = config('filament-panel-base.locale.provider');

        if ($providerClass && class_exists($providerClass) && is_a($providerClass, ProvidesLocales::class, true)) {
            return array_keys($providerClass::getActive());
        }

        return config('filament-panel-base.locale.available', ['en']);
    }

    public function getTranslation(string $locale, ?string $group = null): string
    {
        if ($group === '*' && ! isset($this->text[$locale])) {
            $fallback = config('app.fallback_locale');

            return $this->text[$fallback] ?? $this->key;
        }

        return $this->text[$locale] ?? '';
    }

    public function setTranslation(string $locale, string $value): static
    {
        $this->text = array_merge($this->text ?? [], [$locale => $value]);

        return $this;
    }
}
