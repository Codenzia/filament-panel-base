<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Publishes the Auth module's database migrations + config additions, and
 * runs them. Idempotent — safe to re-run; existing tables/columns are
 * skipped, not overwritten.
 *
 * Usage:
 *   php artisan filament-panel-base:install-auth
 *   php artisan filament-panel-base:install-auth --no-migrate
 */
class InstallAuthCommand extends Command
{
    protected $signature = 'filament-panel-base:install-auth
        {--no-migrate : Publish migrations and config without running them}
        {--with-user-panel : Scaffold a UserPanelProvider in app/Providers/Filament}';

    protected $description = 'Publish and run the codenzia/filament-panel-base authentication module migrations and config.';

    public function handle(): int
    {
        $this->info('Publishing config files...');
        $this->call('vendor:publish', [
            '--tag' => 'filament-panel-base-config',
            '--force' => false,
        ]);

        $this->info('Publishing OTP migration...');
        $this->call('vendor:publish', [
            '--tag' => 'filament-panel-base-auth-migrations',
            '--force' => false,
        ]);

        if ($this->option('with-user-panel')) {
            $this->scaffoldUserPanel();
        }

        if (! $this->option('no-migrate')) {
            $this->info('Running migrations...');
            $this->call('migrate');
        }

        $this->checkUsersTable();

        $this->newLine();
        $this->info('Auth module installed.');
        $this->line('  Next steps:');
        $this->line('   1. Add `withAuthentication()` to your FilamentPanelBasePlugin chain.');
        $this->line('   2. Implement HasPhone, HasModerationStatus on your User model.');
        $this->line('   3. Use traits HasPhoneNumber, ModeratesStatus for default behaviour.');

        return self::SUCCESS;
    }

    private function checkUsersTable(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        $missing = collect(['phone', 'phone_verified_at', 'status'])
            ->filter(fn (string $col) => ! Schema::hasColumn('users', $col))
            ->all();

        if ($missing === []) {
            return;
        }

        $this->newLine();
        $this->warn('Your users table is missing columns required for the auth module:');

        foreach ($missing as $column) {
            $this->line('   - '.$column);
        }

        $this->line('   Create a migration that adds them, e.g.:');
        $this->line('     Schema::table(\'users\', function (Blueprint $table) {');
        $this->line('         $table->string(\'phone\')->nullable()->unique()->after(\'email\');');
        $this->line('         $table->timestamp(\'phone_verified_at\')->nullable()->after(\'email_verified_at\');');
        $this->line('         $table->string(\'status\')->default(\'pending\')->index();');
        $this->line('     });');
    }

    private function scaffoldUserPanel(): void
    {
        $target = app_path('Providers/Filament/UserPanelProvider.php');

        if (file_exists($target)) {
            $this->warn('UserPanelProvider already exists — skipping scaffold.');

            return;
        }

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0o755, recursive: true);
        }

        $stub = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Providers\Filament;

            use Codenzia\FilamentPanelBase\FilamentPanelBasePlugin;
            use Codenzia\FilamentPanelBase\Middleware\EnsureUserApproved;
            use Codenzia\FilamentPanelBase\Providers\BasePanelProvider;
            use Filament\Http\Middleware\Authenticate;
            use Filament\Pages\Dashboard;
            use Filament\Panel;

            class UserPanelProvider extends BasePanelProvider
            {
                public function panel(Panel $panel): Panel
                {
                    return $this->configureSharedSettings(
                        $panel
                            ->id('dashboard')
                            ->path('dashboard')
                            ->login(\App\Livewire\Frontend\Auth\Login::class ?? null)
                            ->pages([Dashboard::class])
                            ->plugin(
                                FilamentPanelBasePlugin::make()
                                    ->withAuthentication(fn ($auth) => $auth
                                        ->credentials('email', 'phone')
                                        ->moderation()
                                        ->requireEmailVerification()
                                        ->verification(driver: 'whatsapp', allowed: ['whatsapp', 'sms', 'email'])
                                        ->disposableEmailBlocking()
                                    )
                            )
                            ->middleware($this->getSharedMiddleware(), append: true)
                            ->authMiddleware([Authenticate::class, EnsureUserApproved::class])
                    );
                }
            }

            PHP;

        file_put_contents($target, $stub);

        $this->info('Scaffolded '.$target);
        $this->line('   Register it in bootstrap/providers.php and adjust auth middleware to suit.');
    }
}
