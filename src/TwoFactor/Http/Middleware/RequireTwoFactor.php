<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\TwoFactor\Http\Middleware;

use Closure;
use Codenzia\FilamentPanelBase\TwoFactor\Concerns\HasTwoFactorAuthentication;
use Codenzia\FilamentPanelBase\TwoFactor\Settings\TwoFactorSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks authenticated users whose role is listed in
 * TwoFactorSettings::$require_for_roles from reaching any page in the
 * panel until they have enrolled and confirmed 2FA.
 *
 * Drop into a panel's middleware stack via:
 *
 *     $panel->authMiddleware([
 *         Authenticate::class,
 *         RequireTwoFactor::class,
 *     ]);
 *
 * Or apply to specific routes only. The middleware is no-op when:
 *  - the settings table is missing (fresh install)
 *  - the 2FA module is disabled
 *  - require_for_roles is empty
 *  - the user is already enrolled
 *  - the user doesn't hold any required role
 *  - the user is on the enrolment/profile page already (avoids redirect loops)
 *
 * Role check requires spatie/laravel-permission's `hasRole()` method on
 * the user model. Without it, the middleware short-circuits — no error.
 */
class RequireTwoFactor
{
    /**
     * @param  array<int, string>  $exemptRoutes Route names that should be allowed
     *                                            through even when 2FA is missing
     *                                            (e.g. logout, profile, the
     *                                            challenge page itself).
     */
    public function handle(Request $request, Closure $next, string ...$exemptRoutes): Response
    {
        $user = Auth::user();

        if ($user === null) {
            return $next($request);
        }

        try {
            /** @var TwoFactorSettings $settings */
            $settings = app(TwoFactorSettings::class);

            if (! $settings->enabled || empty($settings->require_for_roles)) {
                return $next($request);
            }
        } catch (\Throwable) {
            return $next($request);
        }

        if (! in_array(HasTwoFactorAuthentication::class, class_uses_recursive($user), true)) {
            return $next($request);
        }

        if ($user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if (! $this->userHoldsRequiredRole($user, $settings->require_for_roles)) {
            return $next($request);
        }

        $currentRoute = $request->route()?->getName() ?? '';
        $defaultExempt = ['logout', 'two-factor.challenge'];

        foreach (array_merge($defaultExempt, $exemptRoutes) as $exempt) {
            if ($currentRoute === $exempt || str_ends_with($currentRoute, '.'.$exempt)) {
                return $next($request);
            }
        }

        return redirect()->route('two-factor.challenge')->with(
            'status',
            __('filament-panel-base::two-factor.enrolment_required'),
        );
    }

    /**
     * @param  array<int, string>  $required
     */
    private function userHoldsRequiredRole(mixed $user, array $required): bool
    {
        if (! method_exists($user, 'hasAnyRole')) {
            // Without spatie/laravel-permission we can't check roles —
            // fail open so the middleware doesn't lock admins out.
            return false;
        }

        try {
            return (bool) $user->hasAnyRole($required);
        } catch (\Throwable) {
            return false;
        }
    }
}
