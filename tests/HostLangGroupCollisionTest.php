<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Providers\BasePanelProvider;
use Filament\Actions\Action;
use Filament\FilamentManager;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

/**
 * The package keys its own chrome by its English text so a host can override
 * any of it from `lang/{locale}.json`. Laravel resolves such a key as a
 * translation *group* the moment the JSON catalogue misses it, so a host that
 * ships `lang/{locale}/User.php` — or, on a case-insensitive filesystem,
 * `lang/{locale}/user.php`, which is what Toolenza ships — gets that whole
 * file back from `__('User')` as an array. Every Filament page carrying the
 * user menu then died with
 * "Return value must be of type string, array returned".
 */
class _HostLangCollisionUser extends Authenticatable
{
    protected $guarded = [];

    /** @var array<int, string> */
    public array $roles = [];

    /** @return array<int, string> */
    public function roles(): array
    {
        return $this->roles;
    }
}

class HostLangCollisionTestProvider extends BasePanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel;
    }

    public function roleLabel(): string
    {
        return $this->getUserRoleLabel();
    }

    /**
     * Every user-menu chip label, resolved the way Filament resolves them.
     *
     * @return array<int, mixed>
     */
    public function userMenuLabels(Panel $panel): array
    {
        return collect($this->getUserMenuItems($panel))
            ->map(fn ($item) => $item instanceof Closure ? $item(Action::make('probe')) : $item)
            ->map(fn (Action $action) => $action->getLabel())
            ->all();
    }
}

beforeEach(function (): void {
    $this->hostLangPath = sys_get_temp_dir().'/fpb-host-lang-'.uniqid();

    // Both casings: a case-sensitive filesystem needs the exact `User.php` to
    // collide, a case-insensitive one collides through the `user.php` a host
    // would realistically ship.
    foreach (['en', 'ar'] as $locale) {
        File::ensureDirectoryExists($this->hostLangPath.'/'.$locale);

        foreach (['User.php', 'user.php'] as $file) {
            $path = $this->hostLangPath.'/'.$locale.'/'.$file;

            if (File::exists($path)) {
                continue;
            }

            File::put($path, '<?php return ["profile" => "Profile", "roles" => ["admin" => "Admin"]];');
        }
    }

    app('translation.loader')->addPath($this->hostLangPath);

    $manager = new FilamentManager;
    $manager->setCurrentPanel(Panel::make()->id('host-lang-collision-test'));
    app()->instance('filament', $manager);
});

afterEach(function (): void {
    File::deleteDirectory($this->hostLangPath);
    app()->setLocale('en');
});

it('reproduces the array a host lang group file hands back for a bare key', function (): void {
    expect(__('User'))->toBeArray();
});

it('keeps the guarded helper on a string when the host shadows the key', function (): void {
    expect(fpb_trans('User'))->toBeString()->toBe('User');

    app()->setLocale('ar');

    expect(fpb_trans('User'))->toBeString();
});

it('keeps getUserRoleLabel() a string when the host shadows the key', function (): void {
    expect((new HostLangCollisionTestProvider(app()))->roleLabel())
        ->toBeString()
        ->toBe('User');

    app()->setLocale('ar');

    expect((new HostLangCollisionTestProvider(app()))->roleLabel())
        ->toBeString()
        ->toBe('مستخدم');
});

it('keeps getUserRoleLabel() a string for a signed-in user with no roles', function (): void {
    $this->actingAs(new _HostLangCollisionUser(['name' => 'Aya']));

    expect((new HostLangCollisionTestProvider(app()))->roleLabel())
        ->toBeString()
        ->toBe('User');
});

it('renders every user menu chip when the host shadows the key', function (): void {
    $this->actingAs(new _HostLangCollisionUser(['name' => 'Aya']));

    $labels = (new HostLangCollisionTestProvider(app()))
        ->userMenuLabels(Panel::make()->id('host-lang-collision-test'));

    expect($labels)->not->toBeEmpty();

    foreach ($labels as $label) {
        expect($label)->toBeString();
    }
});

it('renders the chrome components when the host shadows the key', function (): void {
    expect(Blade::render('<x-filament-panel-base::powered-by />'))->toBeString();
    expect(Blade::render('<x-filament-panel-base::sidebar-search />'))->toBeString();
});
