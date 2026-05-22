<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Livewire\Demo;

use Codenzia\FilamentPanelBase\Models\DemoSetting;
use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Generic demo landing page.
 *
 * Drop-in self-service page (default route: /demo) for sales demos and QA:
 *  - Password gate (one shared password, env: APP_DEMO_PAGE_PWD).
 *  - Auto-discovered model count tiles (or explicit list from config).
 *  - Listing of all users with one-click "login as" (super_admins excluded).
 *  - Optional Standard/Demo seed buttons — only rendered when the seeder
 *    class actually exists in the host app.
 *  - Footer with build date and PHP/Laravel/Filament versions.
 *
 * Config: filament-panel-base.demo
 * Route registration: handled by FilamentPanelBaseServiceProvider.
 */
class DemoPage extends Component
{
    public string $gatePassword = '';

    public string $gateError = '';

    public string $seederPassword = '';

    public string $seederAction = '';

    public bool $showPasswordModal = false;

    public string $passwordError = '';

    public bool $resetting = false;

    public bool $resettingStandard = false;

    public function mount(): void
    {
        if (session()->get($this->sessionKey()) === true) {
            return;
        }

        $expected = $this->expectedPassword();
        if (($expected === '' || $expected === null)
            && (bool) config('filament-panel-base.demo.allow_empty_password', false)
        ) {
            // Opt-in: auto-unlock when no password is configured. Off by
            // default so /demo is never public unless a password is set.
            session()->put($this->sessionKey(), true);
        }
    }

    public function unlock(): void
    {
        $expected = (string) $this->expectedPassword();

        if ($expected === '' || ! hash_equals($expected, $this->gatePassword)) {
            $this->gateError = __('Incorrect password.');
            $this->gatePassword = '';

            return;
        }

        session()->put($this->sessionKey(), true);
        $this->gateError = '';
        $this->gatePassword = '';

        $this->touchLastUsedAt();
    }

    /**
     * Best-effort write of `last_used_at` on the singleton demo_settings row.
     * Silently no-ops if the table doesn't exist (host hasn't run the new
     * migration yet) so the gate stays functional through the upgrade.
     */
    protected function touchLastUsedAt(): void
    {
        try {
            if (! Schema::hasTable('demo_settings')) {
                return;
            }
            $row = DemoSetting::current();
            $row->last_used_at = now();
            $row->save();
        } catch (\Throwable) {
            // Don't break the gate over a write failure.
        }
    }

    public function lock(): void
    {
        session()->forget($this->sessionKey());
        $this->gatePassword = '';
    }

    public function promptSeeder(string $action): void
    {
        if (! in_array($action, ['standard', 'demo'], true)) {
            return;
        }

        $this->seederAction = $action;
        $this->seederPassword = '';
        $this->passwordError = '';
        $this->showPasswordModal = true;
    }

    public function cancelSeeder(): void
    {
        $this->showPasswordModal = false;
        $this->seederPassword = '';
        $this->passwordError = '';
        $this->seederAction = '';
    }

    public function confirmSeeder(): void
    {
        $expected = (string) $this->expectedPassword();

        if ($expected === '' || ! hash_equals($expected, $this->seederPassword)) {
            $this->passwordError = __('Incorrect password.');

            return;
        }

        $this->showPasswordModal = false;
        $this->seederPassword = '';
        $this->passwordError = '';

        $seeders = (array) config('filament-panel-base.demo.seeders', []);
        $class = $seeders[$this->seederAction] ?? null;

        if (! $class || ! class_exists($class)) {
            return;
        }

        if ($this->seederAction === 'demo') {
            $this->resetting = true;
        } else {
            $this->resettingStandard = true;
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
        Artisan::call('db:seed', ['--class' => $class, '--force' => true]);

        $this->loginFirstAdmin();

        $this->resetting = false;
        $this->resettingStandard = false;
        $this->redirect(url(config('filament-panel-base.demo.route', '/demo')));
    }

    public function loginAs(int|string $userId): void
    {
        if (! session()->get($this->sessionKey())) {
            return;
        }

        $userModel = $this->userModel();
        $user = $userModel ? $userModel::query()->find($userId) : null;

        if (! $user) {
            return;
        }

        if (! $this->canLogInAs($user)) {
            return;
        }

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        Auth::login($user);
        session()->regenerate();
        session()->put($this->sessionKey(), true);

        $this->redirect(url(config('filament-panel-base.demo.app_url', '/admin')));
    }

    public function render()
    {
        $unlocked = (bool) session()->get($this->sessionKey());

        $data = [
            'unlocked' => $unlocked,
            'appUrl' => url(config('filament-panel-base.demo.app_url', '/admin')),
            'sharedPassword' => (string) config('filament-panel-base.demo.shared_user_password', 'password'),
            'footer' => $this->footerData(),
            'seederPasswordHint' => (bool) $this->expectedPassword(),
            'hasStandardSeeder' => $this->seederClassExists('standard'),
            'hasDemoSeeder' => $this->seederClassExists('demo'),
        ];

        if ($unlocked) {
            $data['stats'] = $this->collectStats();
            $data['users'] = $this->collectUsers();
        }

        return view('filament-panel-base::livewire.demo.page', $data)
            ->layout($this->layout());
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    protected function layout(): string
    {
        return (string) config('filament-panel-base.demo.layout', 'filament-panel-base::layouts.demo');
    }

    protected function sessionKey(): string
    {
        return 'filament-panel-base.demo.unlocked';
    }

    protected function expectedPassword(): ?string
    {
        // DB-first: the singleton demo_settings row wins if a password is set.
        // Falls back to .env so a fresh install isn't locked out before the
        // migration is run (and so hosts that never set up the DB row keep
        // the env-only behavior).
        try {
            if (Schema::hasTable('demo_settings')) {
                $row = DemoSetting::current();
                if (is_string($row->password) && $row->password !== '') {
                    return $row->password;
                }
            }
        } catch (\Throwable) {
            // Schema check or DB read failed — fall through to .env.
        }

        $env = (string) config('filament-panel-base.demo.password_env', 'APP_DEMO_PAGE_PWD');
        $value = env($env);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return class-string<Model>|null
     */
    protected function userModel(): ?string
    {
        $class = config('filament-panel-base.user_model');

        return is_string($class) && class_exists($class) ? $class : null;
    }

    protected function isSuperAdmin(Model $user): bool
    {
        $role = (string) config('filament-panel-base.admin_role', 'super_admin');

        if (method_exists($user, 'hasRole')) {
            try {
                return (bool) $user->hasRole($role);
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Authorization hook for "Login as <user>" button clicks. Default
     * implementation blocks super_admins to avoid trivially escalating from
     * a public demo page into the admin account. Subclasses can tighten
     * (e.g., toolenza-style email allowlists) or loosen as appropriate.
     */
    protected function canLogInAs(Model $user): bool
    {
        return ! $this->isSuperAdmin($user);
    }

    protected function seederClassExists(string $action): bool
    {
        $seeders = (array) config('filament-panel-base.demo.seeders', []);
        $class = $seeders[$action] ?? null;

        return is_string($class) && class_exists($class);
    }

    protected function loginFirstAdmin(): void
    {
        $userModel = $this->userModel();
        if (! $userModel) {
            return;
        }

        $email = (string) config('filament-panel-base.demo.admin_email', '');
        $admin = null;

        if ($email !== '') {
            $admin = $userModel::query()->where('email', $email)->first();
        }

        if (! $admin) {
            $admin = $userModel::query()->orderBy('id')->first();
        }

        if ($admin instanceof Model) {
            Auth::login($admin);
            session()->regenerate();
            session()->put($this->sessionKey(), true);
        }
    }

    /**
     * @return list<array{label:string,value:int,icon:string}>
     */
    protected function collectStats(): array
    {
        $explicit = (array) config('filament-panel-base.demo.stats', []);
        if ($explicit !== []) {
            $out = [];
            foreach ($explicit as $entry) {
                $class = $entry['model'] ?? null;
                if (! is_string($class) || ! class_exists($class)) {
                    continue;
                }
                $instance = new $class;
                if (! $instance instanceof Model) {
                    continue;
                }
                $out[] = [
                    'label' => (string) ($entry['label'] ?? Str::headline(class_basename($class))),
                    'value' => (int) $class::query()->count(),
                    'icon' => (string) ($entry['icon'] ?? 'heroicon-o-cube'),
                ];
            }

            return $out;
        }

        return $this->autoDiscoverStats();
    }

    /**
     * @return list<array{label:string,value:int,icon:string}>
     */
    protected function autoDiscoverStats(): array
    {
        $modelsPath = app_path('Models');
        if (! is_dir($modelsPath)) {
            return [];
        }

        $excluded = array_flip((array) config('filament-panel-base.demo.exclude_models', []));
        $stats = [];

        foreach (File::allFiles($modelsPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $class = 'App\\Models\\'.$relative;

            if (! class_exists($class)) {
                continue;
            }

            if (isset($excluded[$class]) || isset($excluded[class_basename($class)])) {
                continue;
            }

            try {
                $reflection = new \ReflectionClass($class);
            } catch (\Throwable) {
                continue;
            }

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            try {
                $count = (int) $class::query()->count();
            } catch (\Throwable) {
                continue;
            }

            $stats[] = [
                'label' => Str::headline(Str::pluralStudly(class_basename($class))),
                'value' => $count,
                'icon' => $this->iconForModel($class),
            ];
        }

        usort($stats, fn ($a, $b) => $b['value'] <=> $a['value']);

        return $stats;
    }

    protected function iconForModel(string $class): string
    {
        $name = Str::lower(class_basename($class));

        return match (true) {
            str_contains($name, 'user') => 'heroicon-o-users',
            str_contains($name, 'team') || str_contains($name, 'workspace') => 'heroicon-o-user-group',
            str_contains($name, 'role') || str_contains($name, 'permission') => 'heroicon-o-shield-check',
            str_contains($name, 'product') => 'heroicon-o-cube',
            str_contains($name, 'order') => 'heroicon-o-shopping-bag',
            str_contains($name, 'invoice') || str_contains($name, 'payment') => 'heroicon-o-banknotes',
            str_contains($name, 'task') => 'heroicon-o-clipboard-document-check',
            str_contains($name, 'project') || str_contains($name, 'pmo') => 'heroicon-o-briefcase',
            str_contains($name, 'comment') => 'heroicon-o-chat-bubble-left-right',
            str_contains($name, 'category') || str_contains($name, 'tag') => 'heroicon-o-tag',
            str_contains($name, 'ticket') => 'heroicon-o-ticket',
            str_contains($name, 'document') || str_contains($name, 'folder') => 'heroicon-o-folder',
            str_contains($name, 'post') || str_contains($name, 'page') || str_contains($name, 'article') => 'heroicon-o-document-text',
            str_contains($name, 'media') || str_contains($name, 'image') => 'heroicon-o-photo',
            str_contains($name, 'notification') => 'heroicon-o-bell',
            str_contains($name, 'setting') => 'heroicon-o-cog-6-tooth',
            default => 'heroicon-o-rectangle-stack',
        };
    }

    /**
     * @return list<array{id:mixed,name:string,email:string,roles:list<string>,is_current:bool,is_super:bool,avatar:?string}>
     */
    protected function collectUsers(): array
    {
        $userModel = $this->userModel();
        if (! $userModel) {
            return [];
        }

        $query = $userModel::query()->orderBy('id');

        $relations = [];
        $instance = new $userModel;
        if (method_exists($instance, 'roles')) {
            $relations[] = 'roles';
        }
        if ($relations !== []) {
            $query->with($relations);
        }

        return $query->get()->map(function (Model $user): array {
            $roles = [];
            if (method_exists($user, 'roles')) {
                try {
                    $roles = $user->roles->pluck('name')->all();
                } catch (\Throwable) {
                    $roles = [];
                }
            }

            return [
                'id' => $user->getKey(),
                'name' => (string) ($user->name ?? $user->email ?? ('#'.$user->getKey())),
                'email' => (string) ($user->email ?? ''),
                'roles' => $roles,
                'is_current' => Auth::id() === $user->getKey(),
                'is_super' => ! $this->canLogInAs($user),
                'avatar' => $this->avatarFor($user),
            ];
        })->all();
    }

    protected function avatarFor(Model $user): ?string
    {
        if (property_exists($user, 'profile_photo_url') || method_exists($user, 'getProfilePhotoUrlAttribute')) {
            $value = $user->profile_photo_url ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $email = (string) ($user->email ?? '');
        if ($email === '') {
            return null;
        }

        return 'https://www.gravatar.com/avatar/'.md5(strtolower($email)).'?d=identicon&s=80';
    }

    /**
     * @return array{built_at:string,php:string,laravel:string,filament:string,app_name:string}
     */
    protected function footerData(): array
    {
        return [
            'built_at' => $this->buildDate(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'filament' => $this->packageVersion('filament/filament'),
            'app_name' => (string) config('app.name'),
        ];
    }

    protected function buildDate(): string
    {
        $explicit = env('APP_BUILD_DATE');
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        $lock = base_path('composer.lock');
        if (is_file($lock)) {
            return date('Y-m-d', filemtime($lock));
        }

        return date('Y-m-d');
    }

    protected function packageVersion(string $package): string
    {
        try {
            if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)) {
                return (string) InstalledVersions::getPrettyVersion($package);
            }
        } catch (\Throwable) {
        }

        return 'n/a';
    }
}
