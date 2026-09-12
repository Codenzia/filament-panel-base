<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Concerns\ModeratesStatus;
use Codenzia\FilamentPanelBase\Auth\Events\UserRegistering;
use Codenzia\FilamentPanelBase\Auth\Settings\AuthenticationSettings;
use Codenzia\FilamentPanelBase\Auth\Support\UnusablePassword;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Codenzia\FilamentPanelBase\Sso\Models\SsoIdentity;
use Codenzia\FilamentPanelBase\Sso\ProviderRegistry;
use Codenzia\FilamentPanelBase\Sso\Support\Base64Url;
use Codenzia\FilamentPanelBase\Tests\Support\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

/**
 * End-to-end cover for the OIDC sign-in flow against a faked identity
 * provider.
 *
 * The IdP is real in every respect that matters: the discovery document, the
 * JWKS and the id_token are generated here from a fixture RSA keypair, and the
 * token is genuinely RS256-signed — so the signature check, the JWKS lookup and
 * the DER key reconstruction are all exercised for real rather than stubbed.
 *
 * The keypair is a fixture rather than freshly generated because
 * `openssl_pkey_new()` reads openssl.cnf and fails on hosts where that file is
 * absent (Windows/Herd, slim containers). Parsing a PEM needs no config, so a
 * baked-in key keeps this suite deterministic everywhere. It is a throwaway
 * test key and protects nothing.
 */

/*
|--------------------------------------------------------------------------
| Fixture identity provider
|--------------------------------------------------------------------------
*/

const SSO_ISSUER = 'https://idp.test';
const SSO_KID = 'fixture-key-1';

function ssoPrivateKeyPem(): string
{
    return <<<'PEM'
        -----BEGIN PRIVATE KEY-----
        MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC1fNZk4Ck90vLa
        oTWE8NW0vf/yrZuUpZJIp7lq1zvBhokplYgwXetGL3CRUG1h77s9MPim8VVD8n5Y
        4zmk98JWNqVUUuwd3JM9H5v1Z1C7fXVHfbuhRHaCaRKDuB5qTgKvaDWw1p9IecLz
        X9RszW0GQsRE0IueL2ENAWteWXRVgSEnTb5N2hXhmMhOhYcJQ+sgnHTvHoAMj7bM
        TsvVZ5eANeRpqyzOxTXIpGLuYKwA+BqMeSsE4bxszhp/nQbC6K0fdkhvWjkPeZNi
        0T8Tu6XowEoc/EiUmkg9+CYu5G/qx5Y0beMd6u62yzEJqy4hHhSaX2fAf0GivPAB
        NqES76k7AgMBAAECggEABM6NcyO7+k1rBz7707PGEg2lbzNv8N9alFGoIVwZn6xe
        vg43pDUbzsKugMeYWt6zOKsVlH5eqF6oDNY/2zTnHcqmu485F8Iu2Cozj3iXQWmd
        c8sXVbpVe52Pn8axorxcNj8hWB5NhU+ysFvnNjzrch4BZGOackWFx+1nGM5vvI/6
        iBIBNgaLfIiisv7W1085sUG16PLkbUryICx2fVYDeuz2gE7d4WXTlpoT4J9jGvuG
        6BVMamMySs5iR9xpRSg7zSdV6w3/BhAhtOt1PSMRRjT0J9ZflbhntX1gDK9viP68
        DjypUoRryH1Ur6c2PkSnwxOIDSJ7knSnO/JIc8t74QKBgQDr6Seh5q3qf8OoSj4t
        XkzVGvIHDBqdPBpYyFs98tozWivosaEoe4iA3hJ1N6kODb3ur7aCIGb6uf6zsNNU
        Ld3mDStp5ht1UXFAi34QYjmOlacGiEHzO+SqKaeSashp0Z83qQk4B8J0GiK5Cyka
        IaZv8Z8elcSyFT1DS+A9B5J/CwKBgQDE8ULMjTMz3ZTvlDN+ZgWoMdJQO+rRS6tX
        1UKYcePX0DKnrEZWwiDOGiNdMhdupGhon6SWl40IXyWbZLR4Y+pHUvVCh9eLe9lX
        FL3oI3eHm1TaOIhGShwzs3cjWJULfdkxemLQ53k5u5zSOe3YFCjLbXqYpnJd+mzu
        Xhlc3YGckQKBgA+LFx9lsYPH0z5dVedrVSidU+D+/Snq8dlzqf9U5ueHQ2lbesDO
        Etpax2CNwEe6xA7b6Ox98gsHAi8YsXPUadBkgb3CeYCrUwjrp+ywbBZm6dBWXfIG
        ujQz9mSBQJ8oPpNOQds9N6SqrCsA/z5HhU3O36sGNyV8nMK4VxfuTcqtAoGARKjX
        4PhZIXvesKe6TBbFYh38dHxvIQiAc96lmQAbruGmx04IN7b2OORj79nG0Yv/2nnN
        p7KuOHzzX3l8cXxj8Pm7B7bULoy++N0CWCwFQAGnU2ziFZ9AtcWbg3cefyMg1V8V
        lD8exEHkKmaHxQ1CK6m/U/izZpAn6fJkZTvUNAECgYEAyQl/T3GyuALBSQGAc6Qw
        3XMbAlX2v6SE3A2euECBw7S6Lcwrm7YZQbwQQYZjKY9xg860gsm6qP6yXuf5zQkf
        v0fbAo9BiHBUUF0KEyttaBTyM4jRqta7zw8vBvBuLruI+t+PS1J4unr+/awCEm/b
        hqRyXViA6zMd4LmOb78487Q=
        -----END PRIVATE KEY-----
        PEM;
}

/**
 * The fixture key's public half, published the way a real IdP would.
 *
 * @return array<string, mixed>
 */
function ssoJwks(): array
{
    $details = openssl_pkey_get_details(openssl_pkey_get_private(ssoPrivateKeyPem()));

    return [
        'keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => SSO_KID,
            'n' => Base64Url::encode($details['rsa']['n']),
            'e' => Base64Url::encode($details['rsa']['e']),
        ]],
    ];
}

/**
 * Mint an id_token. Claims default to a valid token for `ada@acme.test`;
 * `$claims` overrides them and `$header` corrupts the JOSE header.
 *
 * @param  array<string, mixed>  $claims
 * @param  array<string, mixed>  $header
 */
function ssoIdToken(array $claims = [], array $header = [], bool $sign = true, bool $tamper = false): string
{
    $header = array_merge(['typ' => 'JWT', 'alg' => 'RS256', 'kid' => SSO_KID], $header);

    $claims = array_merge([
        'iss' => SSO_ISSUER,
        'aud' => 'client-id',
        'sub' => 'idp-subject-1',
        'exp' => time() + 300,
        'iat' => time(),
        'email' => 'ada@acme.test',
        'email_verified' => true,
        'name' => 'Ada Lovelace',
    ], $claims);

    $signingInput = Base64Url::encode((string) json_encode($header))
        .'.'.Base64Url::encode((string) json_encode($claims));

    $signature = '';

    if ($sign) {
        openssl_sign(
            $signingInput,
            $signature,
            openssl_pkey_get_private(ssoPrivateKeyPem()),
            OPENSSL_ALGO_SHA256,
        );
    }

    if ($tamper) {
        // Keep the genuine signature but swap the payload underneath it —
        // exactly what an attacker who intercepts a token would try.
        $signingInput = Base64Url::encode((string) json_encode($header))
            .'.'.Base64Url::encode((string) json_encode(
                array_merge($claims, ['email' => 'attacker@evil.test', 'sub' => 'attacker'])
            ));
    }

    return $signingInput.'.'.Base64Url::encode($signature);
}

/**
 * What the faked token endpoint should mint on its next call.
 *
 * The nonce is only known after the redirect step has run, and `Http::fake()`
 * MERGES stubs rather than replacing them (so a second fake cannot override
 * the first). Holding the token shape here and letting the stub read it at
 * request time sidesteps that entirely.
 *
 * @param  array<string, mixed>|null  $state
 * @return array<string, mixed>
 */
function ssoTokenState(?array $state = null): array
{
    static $current = ['nonce' => null, 'claims' => [], 'header' => [], 'sign' => true, 'tamper' => false];

    if ($state !== null) {
        $current = $state;
    }

    return $current;
}

/**
 * Register the faked IdP endpoints: discovery, JWKS and a token endpoint that
 * mints its id_token at call time from ssoTokenState().
 */
function ssoFakeIdp(): void
{
    Http::fake([
        SSO_ISSUER.'/.well-known/openid-configuration' => Http::response([
            'issuer' => SSO_ISSUER,
            'authorization_endpoint' => SSO_ISSUER.'/authorize',
            'token_endpoint' => SSO_ISSUER.'/token',
            'jwks_uri' => SSO_ISSUER.'/jwks',
        ]),
        SSO_ISSUER.'/jwks' => Http::response(ssoJwks()),
        SSO_ISSUER.'/token' => function () {
            $state = ssoTokenState();

            $claims = $state['nonce'] === null
                ? $state['claims']
                : array_merge(['nonce' => $state['nonce']], $state['claims']);

            return Http::response([
                'token_type' => 'Bearer',
                'access_token' => 'access-token-value',
                'id_token' => ssoIdToken(
                    $claims,
                    $state['header'],
                    $state['sign'],
                    $state['tamper'] ?? false,
                ),
            ]);
        },
    ]);
}

/**
 * Query parameters of the authorization URL the redirect step produced.
 *
 * @return array<string, string>
 */
function ssoAuthorizeParams(TestResponse $response): array
{
    parse_str(
        (string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY),
        $params,
    );

    /** @var array<string, string> $params */
    return $params;
}

/**
 * Drive the redirect step and return its authorization-URL parameters, with
 * the token endpoint re-faked to answer with the matching nonce.
 *
 * @param  array<string, mixed>  $claims
 * @param  array<string, mixed>  $header
 * @return array<string, string>
 */
function ssoBeginFlow(
    array $claims = [],
    array $header = [],
    bool $sign = true,
    bool $tamper = false,
): array {
    $state = ['nonce' => null, 'claims' => $claims, 'header' => $header, 'sign' => $sign, 'tamper' => $tamper];

    ssoTokenState($state);

    ssoFakeIdp();

    $params = ssoAuthorizeParams(test()->get('/sso/acme/redirect'));

    // Now that the flow exists, the token endpoint can answer with its nonce.
    ssoTokenState(array_merge($state, ['nonce' => $params['nonce'] ?? null]));

    return $params;
}

/*
|--------------------------------------------------------------------------
| Environment
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    // The routes are gated at boot on config; register them at runtime here so
    // the flow can be exercised over HTTP. The boot-time gate itself is
    // covered by tests/Sso/SsoDisabledTest.php.
    Route::middleware(['web'])
        ->prefix('sso')
        ->name('filament-panel-base.sso.')
        ->group(__DIR__.'/../../routes/sso.php');

    Route::getRoutes()->refreshNameLookups();

    $this->createUsersTable();

    // The moderation contract needs somewhere to record its decision.
    Schema::table('users', function (Blueprint $table): void {
        $table->string('status')->nullable();
    });

    // Run the package's real migration rather than hand-rolling the schema, so
    // the table the tests exercise is the table hosts actually get.
    Artisan::call('migrate', [
        '--path' => (string) realpath(__DIR__.'/../../database/migrations/sso'),
        '--realpath' => true,
    ]);

    // The routes carry ThrottleAuth, which reads AuthenticationSettings from
    // the DB. Bind a stub with headroom so the multi-request flows below are
    // never throttled — the throttle itself is covered by its own suite.
    $settings = $this->settingsStub(AuthenticationSettings::class);
    $settings->throttle_per_minute = 1000;
    $settings->throttle_per_day = 10_000;
    app()->instance(AuthenticationSettings::class, $settings);

    config()->set('cache.default', 'array');
    config()->set('filament-panel-base.user_model', TestUser::class);
    config()->set('auth.providers.users.model', TestUser::class);
    Auth::setDefaultDriver('web');

    config()->set('filament-panel-base.sso.enabled', true);
    config()->set('filament-panel-base.sso.auto_provision', false);
    config()->set('filament-panel-base.sso.default_role', null);
    config()->set('filament-panel-base.sso.providers', [
        'acme' => [
            'label' => 'Acme ID',
            'issuer' => SSO_ISSUER,
            'client_id' => 'client-id',
            'client_secret' => 'client-secret',
            'scopes' => ['openid', 'profile', 'email'],
        ],
    ]);
});

/*
|--------------------------------------------------------------------------
| Provider resolution & login buttons
|--------------------------------------------------------------------------
*/

it('renders a Continue with button for each configured provider', function (): void {
    $html = view('filament-panel-base::sso.buttons')->render();

    expect($html)->toContain('Continue with Acme ID')
        ->and($html)->toContain(route('filament-panel-base.sso.redirect', ['provider' => 'acme']));
});

it('hides providers that are missing credentials', function (): void {
    config()->set('filament-panel-base.sso.providers.broken', [
        'label' => 'Half Configured',
        'issuer' => 'https://other.test',
        'client_id' => 'only-an-id',
    ]);

    expect(view('filament-panel-base::sso.buttons')->render())
        ->not->toContain('Half Configured');
});

it('404s a provider that is not configured', function (): void {
    $this->get('/sso/nope/redirect')->assertNotFound();
    $this->get('/sso/nope/callback')->assertNotFound();
});

it('derives the discovery URL from the Google and Entra presets', function (): void {
    config()->set('filament-panel-base.sso.providers', [
        'google' => [
            'preset' => 'google',
            'client_id' => 'id',
            'client_secret' => 'secret',
        ],
        'entra' => [
            'preset' => 'entra',
            'tenant' => 'contoso.onmicrosoft.com',
            'client_id' => 'id',
            'client_secret' => 'secret',
        ],
    ]);

    $providers = app(ProviderRegistry::class)->all();

    expect($providers['google']->discoveryUrl)
        ->toBe('https://accounts.google.com/.well-known/openid-configuration')
        ->and($providers['entra']->discoveryUrl)
        ->toBe('https://login.microsoftonline.com/contoso.onmicrosoft.com/v2.0/.well-known/openid-configuration');
});

/*
|--------------------------------------------------------------------------
| Redirect step
|--------------------------------------------------------------------------
*/

it('sends the browser to the authorization endpoint with state, nonce and PKCE', function (): void {
    ssoFakeIdp();

    $response = $this->get('/sso/acme/redirect');

    $response->assertRedirectContains(SSO_ISSUER.'/authorize');

    $params = ssoAuthorizeParams($response);

    expect($params['client_id'])->toBe('client-id')
        ->and($params['response_type'])->toBe('code')
        ->and($params['scope'])->toBe('openid profile email')
        ->and($params['code_challenge_method'])->toBe('S256')
        ->and($params['state'])->not->toBeEmpty()
        ->and($params['nonce'])->not->toBeEmpty()
        ->and($params['code_challenge'])->not->toBeEmpty()
        ->and($params['redirect_uri'])
        ->toBe(route('filament-panel-base.sso.callback', ['provider' => 'acme']));
});

/*
|--------------------------------------------------------------------------
| Happy path
|--------------------------------------------------------------------------
*/

it('signs in an existing user matched by verified email and links the identity', function (): void {
    $user = TestUser::create([
        'name' => 'Ada',
        'email' => 'ada@acme.test',
        'email_verified_at' => now(),
        'password' => bcrypt('local-password'),
    ]);

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);

    $identity = SsoIdentity::query()->first();

    expect(SsoIdentity::query()->count())->toBe(1)
        ->and($identity->provider)->toBe('acme')
        ->and($identity->subject)->toBe('idp-subject-1')
        ->and($identity->user_id)->toBe($user->id)
        ->and($identity->last_login_at)->not->toBeNull();

    // The local password is untouched — SSO is additive, never a replacement.
    expect($user->fresh()->password)->toBe($user->password);
});

it('completes the PKCE exchange with the verifier matching the challenge it sent', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    Http::assertSent(function ($request) use ($params): bool {
        if ($request->url() !== SSO_ISSUER.'/token') {
            return false;
        }

        $verifier = $request['code_verifier'] ?? '';

        return is_string($verifier)
            && $verifier !== ''
            && Base64Url::encode(hash('sha256', $verifier, true)) === $params['code_challenge']
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'auth-code';
    });
});

it('resolves a returning user by subject even after their email changes', function (): void {
    $user = TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    SsoIdentity::query()->create([
        'user_id' => $user->id,
        'provider' => 'acme',
        'issuer' => SSO_ISSUER,
        'subject' => 'idp-subject-1',
        'last_login_at' => now()->subYear(),
    ]);

    // The provider now reports a different address for the same subject.
    $params = ssoBeginFlow(['email' => 'ada.lovelace@acme.test']);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertAuthenticatedAs($user);

    // Still one identity row, its last_login_at refreshed — an upsert, not an insert.
    expect(SsoIdentity::query()->count())->toBe(1)
        ->and(SsoIdentity::query()->first()->last_login_at->isToday())->toBeTrue()
        ->and(TestUser::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Provisioning
|--------------------------------------------------------------------------
*/

it('refuses an unmatched email while auto-provisioning is off', function (): void {
    $params = ssoBeginFlow(['email' => 'stranger@acme.test']);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state'])
        ->assertRedirect();

    $this->assertGuest();

    expect(TestUser::query()->count())->toBe(0)
        ->and(SsoIdentity::query()->count())->toBe(0)
        ->and(session('error'))
        ->toBe(__('filament-panel-base::auth.sso_no_account', ['provider' => 'Acme ID']));
});

it('creates the user and assigns the default role when auto-provisioning is on', function (): void {
    config()->set('filament-panel-base.user_model', SsoRoleTestUser::class);
    config()->set('auth.providers.users.model', SsoRoleTestUser::class);
    config()->set('filament-panel-base.sso.auto_provision', true);
    config()->set('filament-panel-base.sso.default_role', 'editor');

    SsoRoleTestUser::$assignedRoles = [];

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $user = SsoRoleTestUser::query()->first();

    expect(SsoRoleTestUser::query()->count())->toBe(1)
        ->and($user->email)->toBe('ada@acme.test')
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(SsoRoleTestUser::$assignedRoles)->toBe(['editor']);

    $this->assertAuthenticatedAs($user);

    expect(SsoIdentity::query()->count())->toBe(1)
        ->and(SsoIdentity::query()->first()->user_id)->toBe($user->id);
});

it('lower-cases the email claim so a mixed-case address matches one account', function (): void {
    $user = TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['email' => 'Ada@Acme.Test']);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertAuthenticatedAs($user);
    expect(TestUser::query()->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Security
|--------------------------------------------------------------------------
*/

it('rejects a callback whose state does not match the stored flow', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state=forged-state')
        ->assertRedirect();

    $this->assertGuest();
    expect(session('error'))->toBe(__('filament-panel-base::auth.sso_invalid_state'));
});

it('rejects a callback with no flow in the session at all', function (): void {
    ssoFakeIdp();

    $this->get('/sso/acme/callback?code=auth-code&state=whatever')
        ->assertRedirect();

    $this->assertGuest();
    expect(session('error'))->toBe(__('filament-panel-base::auth.sso_invalid_state'));
});

it('rejects a replayed callback because the flow is single-use', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);
    $this->assertAuthenticated();

    Auth::logout();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
    expect(session('error'))->toBe(__('filament-panel-base::auth.sso_invalid_state'));
});

it('rejects an id_token whose nonce does not match the flow', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['nonce' => 'a-different-nonce']);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state'])
        ->assertRedirect();

    $this->assertGuest();
    expect(session('error'))->toBe(__('filament-panel-base::auth.sso_invalid_state'));
});

it('rejects an id_token that is not signed by the published JWKS key', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(sign: false);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
    expect(session('error'))
        ->toBe(__('filament-panel-base::auth.sso_provider_error', ['provider' => 'Acme ID']));
});

it('rejects an id_token whose payload was swapped under a valid signature', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);
    TestUser::create(['name' => 'Mallory', 'email' => 'attacker@evil.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(tamper: true);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();

    expect(SsoIdentity::query()->count())->toBe(0)
        ->and(session('error'))
        ->toBe(__('filament-panel-base::auth.sso_provider_error', ['provider' => 'Acme ID']));
});

it('rejects an unsigned alg:none id_token', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(header: ['alg' => 'none'], sign: false);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
});

it('rejects an id_token signed with an unknown key id', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(header: ['kid' => 'rotated-away']);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
});

it('rejects an id_token issued for a different audience', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['aud' => 'someone-elses-client']);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
});

it('rejects an id_token from a different issuer', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['iss' => 'https://evil.test']);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
});

it('rejects an expired id_token', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['exp' => time() - 3600]);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
});

it('rejects an unverified email claim by default', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['email_verified' => false]);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();

    expect(SsoIdentity::query()->count())->toBe(0)
        ->and(session('error'))
        ->toBe(__('filament-panel-base::auth.sso_unverified_email', ['provider' => 'Acme ID']));
});

it('accepts an unverified email when the provider opts in', function (): void {
    config()->set('filament-panel-base.sso.providers.acme.allow_unverified_email', true);

    $user = TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['email_verified' => false]);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertAuthenticatedAs($user);
});

it('surfaces a provider-side error without echoing its description', function (): void {
    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?state='.$params['state']
        .'&error=access_denied&error_description=Very+specific+internal+detail')
        ->assertRedirect();

    $this->assertGuest();

    expect(session('error'))
        ->toBe(__('filament-panel-base::auth.sso_provider_error', ['provider' => 'Acme ID']))
        ->and(session('error'))->not->toContain('Very specific internal detail');
});

/**
 * User model exposing a role system, so the auto-provisioning path's
 * `assignRole()` call can be observed without pulling in spatie/laravel-permission.
 */
class SsoRoleTestUser extends TestUser
{
    /** @var array<int, string> */
    public static array $assignedRoles = [];

    protected $table = 'users';

    public function assignRole(string $role): void
    {
        static::$assignedRoles[] = $role;
    }
}

/*
|--------------------------------------------------------------------------
| Account linking and provisioning policy
|--------------------------------------------------------------------------
*/

it('refuses to link a verified provider identity into an unverified local account (PB-01)', function (): void {
    // The classic pre-registration takeover: someone claims the address
    // locally with a password of their choosing and never verifies it.
    $squatted = TestUser::create([
        'name' => 'Not Ada',
        'email' => 'ada@acme.test',
        'password' => bcrypt('attacker-password'),
    ]);

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state'])
        ->assertRedirect();

    $this->assertGuest();

    expect(SsoIdentity::query()->count())->toBe(0)
        ->and($squatted->fresh()->password)->toBe($squatted->password)
        ->and(session('error'))->toBe(__(
            'filament-panel-base::auth.sso_link_unverified_account',
            ['provider' => 'Acme ID'],
        ));
});

it('leaves an auto-provisioned user pending when registration is moderated (PB-02)', function (): void {
    config()->set('filament-panel-base.user_model', SsoModeratedTestUser::class);
    config()->set('auth.providers.users.model', SsoModeratedTestUser::class);
    config()->set('filament-panel-base.sso.auto_provision', true);

    app(AuthenticationSettings::class)->registration_mode = 'moderated';

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state'])
        ->assertRedirect();

    $this->assertGuest();

    expect(SsoModeratedTestUser::query()->first()->status)->toBe('pending')
        ->and(session('error'))->toBe(__('filament-panel-base::auth.account_pending'));
});

it('approves an auto-provisioned user only when the provider opts in (PB-02)', function (): void {
    config()->set('filament-panel-base.user_model', SsoModeratedTestUser::class);
    config()->set('auth.providers.users.model', SsoModeratedTestUser::class);
    config()->set('filament-panel-base.sso.auto_provision', true);
    config()->set('filament-panel-base.sso.providers.acme.auto_approve', true);

    app(AuthenticationSettings::class)->registration_mode = 'moderated';

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $user = SsoModeratedTestUser::query()->first();

    expect($user->status)->toBe('approved');
    $this->assertAuthenticatedAs($user);
});

it('applies the registration domain allowlist to auto-provisioning (PB-02)', function (): void {
    config()->set('filament-panel-base.sso.auto_provision', true);

    app(AuthenticationSettings::class)->allowed_email_domains = ['staff.test'];

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state'])
        ->assertRedirect();

    $this->assertGuest();

    expect(TestUser::query()->count())->toBe(0)
        ->and(SsoIdentity::query()->count())->toBe(0)
        ->and(session('error'))->toBe(__(
            'filament-panel-base::auth.sso_domain_not_allowed',
            ['provider' => 'Acme ID'],
        ));
});

it('leaves no user or identity behind when a listener cancels the registration (PB-02)', function (): void {
    config()->set('filament-panel-base.sso.auto_provision', true);

    Event::listen(UserRegistering::class, function (UserRegistering $event): void {
        $event->cancel('not invited');
    });

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state'])
        ->assertRedirect();

    $this->assertGuest();

    expect(TestUser::query()->count())->toBe(0)
        ->and(SsoIdentity::query()->count())->toBe(0);
});

it('does not mark an unverified claim as a verified local address (PB-02)', function (): void {
    config()->set('filament-panel-base.sso.auto_provision', true);
    config()->set('filament-panel-base.sso.providers.acme.allow_unverified_email', true);

    $params = ssoBeginFlow(['email_verified' => false]);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    expect(TestUser::query()->first()->email_verified_at)->toBeNull();
});

it('gives an auto-provisioned account no usable password (PB-07)', function (): void {
    config()->set('filament-panel-base.sso.auto_provision', true);

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $user = TestUser::query()->first();

    expect(UnusablePassword::is($user->password))->toBeTrue()
        ->and(Hash::check('', $user->password))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Issuer identity
|--------------------------------------------------------------------------
*/

it('does not resolve a subject from one issuer onto another issuer user (PB-08)', function (): void {
    $previous = TestUser::create([
        'name' => 'Previous tenant',
        'email' => 'someone.else@acme.test',
        'email_verified_at' => now(),
    ]);

    // The provider entry used to point at a different IdP, which minted the
    // same subject string for a completely different person.
    SsoIdentity::query()->create([
        'user_id' => $previous->id,
        'provider' => 'acme',
        'issuer' => 'https://old-idp.test',
        'subject' => 'idp-subject-1',
    ]);

    $ada = TestUser::create([
        'name' => 'Ada',
        'email' => 'ada@acme.test',
        'email_verified_at' => now(),
    ]);

    $params = ssoBeginFlow();

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertAuthenticatedAs($ada);

    expect(SsoIdentity::query()->count())->toBe(2)
        ->and(SsoIdentity::query()->where('issuer', SSO_ISSUER)->first()->user_id)->toBe($ada->id);
});

/*
|--------------------------------------------------------------------------
| Claim validation
|--------------------------------------------------------------------------
*/

it('rejects an id_token with no iat claim (PB-09)', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['iat' => null]);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
});

it('rejects an id_token whose azp names another client (PB-09)', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['azp' => 'someone-elses-client']);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
});

it('rejects a multi-audience id_token that omits azp (PB-09)', function (): void {
    TestUser::create(['name' => 'Ada', 'email' => 'ada@acme.test', 'email_verified_at' => now()]);

    $params = ssoBeginFlow(['aud' => ['client-id', 'another-client']]);

    $this->get('/sso/acme/callback?code=auth-code&state='.$params['state']);

    $this->assertGuest();
});

/**
 * A user model that opts into the package's moderation contract, so the
 * provisioning path's admission decision is observable.
 */
class SsoModeratedTestUser extends TestUser implements HasModerationStatus
{
    use ModeratesStatus;

    protected $table = 'users';
}
