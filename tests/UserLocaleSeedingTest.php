<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * SetLocale read session → cookie → app default and never looked at the signed-in
 * account, so a user whose row says Arabic still landed on an English panel the
 * first time they signed in on a new device. Hosts worked around it with their own
 * middleware copying the column into the session (gamephoria's `primary_locale`).
 * `locale.user_attribute` moves that into the package, opt-in and narrow.
 */
function localeRequest(?object $user = null, array $session = []): Request
{
    $request = Request::create('/test');
    $request->setLaravelSession(app('session.store'));

    foreach ($session as $key => $value) {
        $request->session()->put($key, $value);
    }

    if ($user !== null) {
        $request->setUserResolver(fn () => $user);
    }

    return $request;
}

function runSetLocale(Request $request): void
{
    (new SetLocale)->handle($request, fn () => new Response);
}

function localeUser(mixed $value, string $attribute = 'primary_locale'): object
{
    return new class($attribute, $value)
    {
        public function __construct(private string $attribute, private mixed $value) {}

        public function __get(string $name): mixed
        {
            return $name === $this->attribute ? $this->value : null;
        }

        public function __isset(string $name): bool
        {
            return $name === $this->attribute;
        }
    };
}

beforeEach(function (): void {
    config([
        'app.locale' => 'en',
        'filament-panel-base.locale.available' => ['en', 'ar'],
        'filament-panel-base.locale.user_attribute' => 'primary_locale',
    ]);

    app('session.store')->flush();
});

afterEach(fn () => App::setLocale('en'));

it('seeds the session from the signed-in user stored locale', function (): void {
    $request = localeRequest(localeUser('ar'));

    runSetLocale($request);

    expect(App::getLocale())->toBe('ar')
        ->and($request->session()->get('locale'))->toBe('ar');
});

it('lets an explicit in-session choice beat the stored preference', function (): void {
    $request = localeRequest(localeUser('ar'), ['locale' => 'en']);

    runSetLocale($request);

    expect(App::getLocale())->toBe('en')
        ->and($request->session()->get('locale'))->toBe('en');
});

it('ignores a stored locale that is not in the available list', function (): void {
    $request = localeRequest(localeUser('fr'));

    runSetLocale($request);

    expect(App::getLocale())->toBe('en')
        ->and($request->session()->has('locale'))->toBeFalse();
});

it('ignores an empty or non-string stored value', function (mixed $stored): void {
    $request = localeRequest(localeUser($stored));

    runSetLocale($request);

    expect(App::getLocale())->toBe('en')
        ->and($request->session()->has('locale'))->toBeFalse();
})->with([
    'null' => [null],
    'empty string' => [''],
    'integer' => [0],
    'array' => [[['ar']]],
]);

it('leaves guests alone', function (): void {
    $request = localeRequest();

    runSetLocale($request);

    expect(App::getLocale())->toBe('en')
        ->and($request->session()->has('locale'))->toBeFalse();
});

it('does nothing at all while the attribute is unconfigured', function (): void {
    config(['filament-panel-base.locale.user_attribute' => null]);

    $request = localeRequest(localeUser('ar'));

    runSetLocale($request);

    expect(App::getLocale())->toBe('en')
        ->and($request->session()->has('locale'))->toBeFalse();
});

it('ignores a user with no such attribute', function (): void {
    $request = localeRequest(localeUser('ar', attribute: 'some_other_column'));

    runSetLocale($request);

    expect(App::getLocale())->toBe('en')
        ->and($request->session()->has('locale'))->toBeFalse();
});

it('reads whatever column the host names', function (): void {
    config(['filament-panel-base.locale.user_attribute' => 'ui_language']);

    $request = localeRequest(localeUser('ar', attribute: 'ui_language'));

    runSetLocale($request);

    expect(App::getLocale())->toBe('ar');
});

it('seeds once, so a later switch away from the stored locale sticks', function (): void {
    $user = localeUser('ar');

    $first = localeRequest($user);
    runSetLocale($first);
    expect(App::getLocale())->toBe('ar');

    // The user picks English through the switcher, which sessions the choice.
    app('session.store')->put('locale', 'en');

    $second = localeRequest($user);
    runSetLocale($second);

    expect(App::getLocale())->toBe('en')
        ->and($second->session()->get('locale'))->toBe('en');
});

it('still prefers the session over the cookie when no attribute is configured', function (): void {
    config(['filament-panel-base.locale.user_attribute' => null]);

    $request = localeRequest(null, ['locale' => 'ar']);

    runSetLocale($request);

    expect(App::getLocale())->toBe('ar');
});
