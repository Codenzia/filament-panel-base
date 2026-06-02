<?php

use Codenzia\FilamentPanelBase\Concerns\HasPreferredLocale;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class TestPreferredLocaleUser extends Model implements HasLocalePreference
{
    use HasPreferredLocale;
    use Notifiable;

    protected $guarded = [];

    public $timestamps = false;
}

class TestPreferredLocaleUserCustom extends Model implements HasLocalePreference
{
    use HasPreferredLocale;
    use Notifiable;

    protected $guarded = [];

    public $timestamps = false;

    protected string $preferredLocaleAttribute = 'ui_lang';
}

it('returns the value of the locale attribute', function () {
    $user = new TestPreferredLocaleUser(['locale' => 'ar']);

    expect($user->preferredLocale())->toBe('ar');
});

it('falls back to config(app.locale) when the attribute is empty', function () {
    config(['app.locale' => 'fr']);

    $user = new TestPreferredLocaleUser(['locale' => null]);

    expect($user->preferredLocale())->toBe('fr');
});

it('honours a custom preferredLocaleAttribute', function () {
    $user = new TestPreferredLocaleUserCustom(['ui_lang' => 'he']);

    expect($user->preferredLocale())->toBe('he');
});

it('implements the HasLocalePreference contract so Laravel auto-localises notifications', function () {
    // Laravel's NotificationSender wraps each send in
    // withLocale($notifiable->preferredLocale(), ...) whenever the
    // notifiable implements HasLocalePreference. The contract check
    // here is the load-bearing part — Laravel's framework tests cover
    // the wrap-and-dispatch behaviour, not us.
    $user = new TestPreferredLocaleUser(['locale' => 'ar']);

    expect($user)->toBeInstanceOf(HasLocalePreference::class)
        ->and($user->preferredLocale())->toBe('ar');
});
