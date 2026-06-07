<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Filament\Resources;

use Codenzia\FilamentPanelBase\Filament\Resources\UserResource\Pages;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * Shared admin Users resource — opt in per panel with
 * FilamentPanelBasePlugin::make()->withUserManagement(). Generalized from the
 * fleet's many hand-rolled UserResources into one consistent UI (fix once):
 *
 *  - a tabbed form (Account · Roles · any app-specific tabs via extraSchema)
 *  - password optional on edit (kept only when filled), confirmed on create
 *  - role assignment that appears ONLY when spatie/permission is installed and
 *    the user model uses HasRoles (graceful — it's a soft dependency)
 *  - the protected super-admin (laravel-superadmin) is guarded from deletion
 *
 * It's opt-in precisely because not every panel is an admin panel — a customer
 * dashboard simply never calls withUserManagement(). Access is the host's call
 * via the authorize closure (defaults to super-admin when available).
 */
class UserResource extends Resource
{
    protected static bool $isScopedToTenant = false;

    protected static ?string $recordTitleAttribute = 'name';

    /** Host-supplied gate (set by withUserManagement). Kept as a static, not config, so config:cache stays closure-free. */
    public static ?\Closure $authorizeUsing = null;

    /** Host-supplied extra schema components, appended after the built-in tabs. */
    public static ?\Closure $extraSchemaUsing = null;

    /**
     * Table customizers. Each receives the built-in set and returns the final one,
     * so a host can append (Department, State), prepend (an avatar/Photo column) or
     * reorder — keeping a richer per-app table (e.g. task-off) on the shared base.
     * App-specific columns (avatar via filament-media, a status switcher, a
     * Department relationship) live here, not in the package.
     *
     * @var (\Closure(array): array)|null
     */
    public static ?\Closure $columnsUsing = null;

    /** @var (\Closure(array): array)|null */
    public static ?\Closure $filtersUsing = null;

    /** @var (\Closure(array): array)|null */
    public static ?\Closure $recordActionsUsing = null;

    public static function getModel(): string
    {
        return config('filament-panel-base.user_management.model')
            ?: (config('auth.providers.users.model') ?: 'App\\Models\\User');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return config('filament-panel-base.user_management.navigation_icon', 'heroicon-o-users');
    }

    public static function getNavigationGroup(): ?string
    {
        return __(config('filament-panel-base.user_management.navigation_group', 'User Management'));
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-panel-base.user_management.navigation_sort', 10);
    }

    public static function getNavigationLabel(): string
    {
        return __('Users');
    }

    public static function getModelLabel(): string
    {
        return __('User');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Users');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::query()->count();
    }

    /** Access is the host's decision; default to the super-admin when that concept exists. */
    public static function canAccess(): bool
    {
        if (static::$authorizeUsing !== null) {
            return (bool) (static::$authorizeUsing)();
        }

        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        // laravel-superadmin is the fleet authority on "is this a super-admin?"
        // (the protected account OR the configured super-admin role). Delegate to
        // it so the definition lives in one place across every panel.
        if (class_exists(\Codenzia\SuperAdmin\Facades\SuperAdmin::class)) {
            return \Codenzia\SuperAdmin\Facades\SuperAdmin::isSuperAdmin($user);
        }

        // Spatie roles without laravel-superadmin → gate on the super-admin role.
        if (method_exists($user, 'hasRole')) {
            return (bool) $user->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
        }

        // No admin concept present → the panel's own auth is the gate.
        return true;
    }

    /** Roles are only offered when spatie/permission is installed and the model uses HasRoles. */
    public static function rolesSupported(): bool
    {
        return class_exists(Role::class)
            && method_exists(static::getModel(), 'roles');
    }

    public static function form(Schema $schema): Schema
    {
        $tabs = [
            Tab::make(__('Account'))
                ->icon('heroicon-o-user')
                ->schema([
                    Section::make()->schema([
                        TextInput::make('name')
                            ->label(__('Name'))->required()->maxLength(255)->columnSpanFull(),
                        TextInput::make('email')
                            ->label(__('Email'))->email()->required()->maxLength(255)
                            ->unique(ignoreRecord: true)->columnSpanFull(),
                        TextInput::make('password')
                            ->label(__('Password'))->password()->revealable()->maxLength(255)
                            // Kept only when filled, so editing without retyping leaves it unchanged.
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? __('Leave blank to keep the current password.') : null)
                            ->columnSpanFull(),
                        TextInput::make('password_confirmation')
                            ->label(__('Confirm password'))->password()->revealable()->maxLength(255)
                            ->dehydrated(false)->same('password')
                            ->required(fn (Get $get, string $operation): bool => $operation === 'create' || filled($get('password')))
                            ->columnSpanFull(),
                    ]),
                ]),
        ];

        if (static::rolesSupported()) {
            $tabs[] = Tab::make(__('Roles'))
                ->icon('heroicon-o-user-group')
                ->schema([
                    Section::make()->schema([
                        Select::make('roles')
                            ->label(__('Roles'))
                            ->relationship('roles', 'name')
                            ->multiple()->preload()->searchable()
                            ->helperText(__('Roles granted to this user.'))
                            ->columnSpanFull(),
                    ]),
                ]);
        }

        $components = [Tabs::make()->tabs($tabs)->persistTabInQueryString()->columnSpanFull()];

        if (static::$extraSchemaUsing !== null) {
            $components = array_merge($components, (array) (static::$extraSchemaUsing)());
        }

        return $schema->components($components);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            // Circular avatar via the panel's avatar provider (UI-avatars by default) —
            // works for any user model with no extra packages. Apps with their own
            // avatars (e.g. filament-media) swap this through the tableColumns hook.
            ImageColumn::make('avatar')->label(__('Photo'))->circular()->grow(false)
                ->state(fn ($record): string => Filament::getUserAvatarUrl($record)),
            TextColumn::make('name')->label(__('Name'))->searchable()->sortable()->weight('medium'),
            TextColumn::make('email')->label(__('Email'))->searchable()->sortable()->color('gray')->copyable(),
        ];

        if (static::rolesSupported()) {
            $columns[] = TextColumn::make('roles.name')->label(__('Roles'))->badge()->toggleable();
        }

        $columns[] = IconColumn::make('email_verified_at')->label(__('Verified'))->boolean()
            ->state(fn ($record): bool => filled($record->email_verified_at ?? null))->toggleable();
        $columns[] = TextColumn::make('created_at')->label(__('Joined'))->since()->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

        $filters = [];
        if (static::rolesSupported()) {
            $filters[] = SelectFilter::make('roles')->label(__('Role'))
                ->relationship('roles', 'name')->searchable()->preload();
        }

        $recordActions = [
            EditAction::make()->slideOver(),
            // The protected super-admin (laravel-superadmin) can't be deleted from the UI.
            DeleteAction::make()->visible(fn ($record): bool => ! (bool) ($record->is_protected ?? false)),
        ];

        // Host customizers: append / prepend / reorder to match a richer per-app table.
        if (static::$columnsUsing !== null) {
            $columns = (array) (static::$columnsUsing)($columns);
        }
        if (static::$filtersUsing !== null) {
            $filters = (array) (static::$filtersUsing)($filters);
        }
        if (static::$recordActionsUsing !== null) {
            $recordActions = (array) (static::$recordActionsUsing)($recordActions);
        }

        return $table
            ->columns($columns)
            ->filters($filters)
            ->defaultSort('name')
            // Collapsed into a "⋮" menu (task-off style), keeping the row tidy.
            ->recordActions([
                ActionGroup::make($recordActions),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
