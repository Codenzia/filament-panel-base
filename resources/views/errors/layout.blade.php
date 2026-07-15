{{--
    Branded, fleet-wide error page chrome for filament-panel-base.

    DATABASE-INDEPENDENT BY DESIGN. This view must render even when the
    database is down (the outage that motivates it is a DB failure), so it
    deliberately avoids everything that would touch the DB or app runtime:
      - no __()/@lang — the spatie translation-loader queries language_lines
      - no Eloquent / no queries, no auth, no host app layout (@extends)
    Branding comes only from cached config() reads + a static asset(). The
    bilingual copy (EN + AR) is baked into the per-code child views.

    Registered under the `filament-panel-base` view namespace, so the code
    pages reference it as `filament-panel-base::errors.layout` — a bare
    `errors.layout` an app could shadow is intentionally never used.
--}}
@php
    $primary = config('filament-panel-base.colors.primary') ?: '#0787F8';
    $appName = (string) (config('app.name') ?: 'App');
    $logo = config('filament-panel-base.errors.logo');
    $tagline = config('filament-panel-base.errors.tagline') ?: $appName;
    $supportEmail = config('filament-panel-base.support_email');

    $code = trim($__env->yieldContent('code'));
    $isServerError = $code === '500';

    // Reference ID for 500s only: short, human-readable, logged to the file
    // log so support can grep by it. The framework already logged the
    // underlying exception on the same request/timestamp, so the reference
    // correlates. Everything here is DB-free.
    $reference = null;
    if ($isServerError) {
        $prefix = \Illuminate\Support\Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $appName));
        $prefix = $prefix !== '' ? substr($prefix, 0, 8) : 'REF';
        $reference = $prefix.'-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(8));

        try {
            $url = request()?->fullUrl() ?? '';
        } catch (\Throwable) {
            $url = '';
        }

        // Default (file-based) log channel — never the DB.
        \Illuminate\Support\Facades\Log::error('Branded error page shown', [
            'reference' => $reference,
            'status' => 500,
            'url' => $url,
        ]);

        $timestamp = now()->toIso8601String();
        $mailtoSubject = rawurlencode($appName.' error '.$code.' ['.$reference.']');
        $mailtoBody = rawurlencode(
            "Please describe what you were doing when this happened:\n\n\n"
            ."----------\n"
            ."Reference: {$reference}\n"
            ."Page: {$url}\n"
            ."Time: {$timestamp}"
        );
    }
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title') — {{ $appName }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: "Segoe UI", system-ui, -apple-system, Arial, sans-serif;
        min-height: 100vh; display: flex; align-items: center; justify-content: center;
        padding: 24px; color: #e9eefb;
        background:
            radial-gradient(circle at 68% -5%, rgba(7, 91, 199, 0.12), transparent 34%),
            linear-gradient(135deg, #000a1d 0%, #010e27 55%, #00091a 100%);
    }
    .card { width: 100%; max-width: 560px; text-align: center; padding: 32px; }
    .card img.logo { height: 96px; width: auto; margin-bottom: 8px; }
    .wordmark { font-size: 30px; font-weight: 800; letter-spacing: 1px; color: #fff; margin-bottom: 8px; }
    .code { font-size: 66px; font-weight: 800; line-height: 1; color: {{ $primary }}; margin: 26px 0 6px; letter-spacing: 1px; }
    h1 { font-size: 22px; color: #fff; margin-bottom: 10px; }
    .msg { font-size: 14px; color: #8a97b4; line-height: 1.6; }
    .msg .ar { display: block; margin-top: 7px; color: #64719a; direction: rtl; }
    .actions { margin-top: 28px; display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .btn {
        display: inline-block; background: {{ $primary }}; color: #fff;
        text-decoration: none; font-weight: 700; font-size: 14px;
        padding: 12px 28px; border-radius: 12px; transition: filter 0.15s;
    }
    .btn:hover { filter: brightness(1.1); }
    .btn.ghost { background: transparent; border: 1px solid rgba(255, 255, 255, 0.14); color: #9fb0d0; }
    .btn.ghost:hover { filter: none; border-color: rgba(7, 135, 248, 0.5); color: #cfe0f5; }
    .ref {
        margin-top: 22px; display: inline-flex; align-items: center; gap: 9px; font-size: 12px; color: #64719a;
        background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.07); border-radius: 10px; padding: 7px 8px 7px 13px;
    }
    .ref code { color: #9fb0d0; font-family: Consolas, "Courier New", monospace; letter-spacing: .5px; }
    .copybtn { border: 0; cursor: pointer; background: rgba(7, 135, 248, 0.16); color: #5cb4ff; font: inherit; font-size: 11px; font-weight: 700; padding: 5px 11px; border-radius: 7px; transition: .15s; }
    .copybtn:hover { background: rgba(7, 135, 248, 0.30); }
    .foot { margin-top: 24px; font-size: 11px; color: #4a5678; }
</style>
</head>
<body>
    <div class="card">
        @if ($logo)
            <img class="logo" src="{{ asset($logo) }}" alt="{{ $appName }}">
        @else
            <div class="wordmark">{{ $appName }}</div>
        @endif

        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p class="msg"><span>@yield('en')</span><span class="ar">@yield('ar')</span></p>

        <div class="actions">
            <a class="btn" href="{{ url('/') }}">@yield('cta', 'Back to home')</a>
            @if ($isServerError && $supportEmail)
                <a class="btn ghost" href="mailto:{{ $supportEmail }}?subject={{ $mailtoSubject }}&body={{ $mailtoBody }}">Report this issue</a>
            @endif
        </div>

        @if ($isServerError)
            <div class="ref">
                <span>Reference</span>&nbsp;<code id="pnb-ref">{{ $reference }}</code>
                <button class="copybtn" type="button" id="pnb-copy" data-ref="{{ $reference }}">Copy</button>
            </div>
        @endif

        <div class="foot">{{ $tagline }}</div>
    </div>

    @if ($isServerError)
    <script>
        (function () {
            var btn = document.getElementById('pnb-copy');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var ref = btn.getAttribute('data-ref') || '';
                if (navigator.clipboard) { navigator.clipboard.writeText(ref); }
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1400);
            });
        })();
    </script>
    @endif
</body>
</html>
