<?php

namespace Codenzia\FilamentPanelBase\Middleware;

use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Only enforce moderation if user implements the contract
        if (! $user instanceof HasModerationStatus) {
            return $next($request);
        }

        if ($user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', __('Your account has been suspended. Please contact support.'));
        }

        if ($user->isPending()) {
            return redirect()->route('home')
                ->with('warning', __('Your account is awaiting approval. You will be notified when approved.'));
        }

        return $next($request);
    }
}
