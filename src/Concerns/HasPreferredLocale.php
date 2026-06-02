<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Concerns;

/**
 * Implements Laravel's HasLocalePreference contract so notifications
 * sent via Notification::send() / Mail::to() auto-switch to the user's
 * preferred locale (set on a Notifiable user model, read from a model
 * attribute by default).
 *
 * Usage:
 *
 *   class User extends Authenticatable implements HasLocalePreference
 *   {
 *       use HasPreferredLocale;
 *   }
 *
 * Override `preferredLocaleAttribute()` to point at a different column,
 * or `preferredLocale()` directly if the value lives outside the model
 * (e.g. in a settings table).
 *
 * The trait deliberately falls back to the app locale instead of null
 * so a missing preference never blows up Notification dispatching with
 * an "Unsupported locale" error from translation files that don't exist.
 */
trait HasPreferredLocale
{
    public function preferredLocale(): ?string
    {
        $attribute = $this->preferredLocaleAttribute();
        $value = $this->getAttribute($attribute);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return config('app.locale');
    }

    protected function preferredLocaleAttribute(): string
    {
        return property_exists($this, 'preferredLocaleAttribute')
            ? (string) $this->preferredLocaleAttribute
            : 'locale';
    }
}
