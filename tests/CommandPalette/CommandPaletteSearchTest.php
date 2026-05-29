<?php

use Codenzia\FilamentPanelBase\CommandPalette\CommandPaletteRegistry;
use Codenzia\FilamentPanelBase\CommandPalette\Data\CommandPaletteAction;
use Codenzia\FilamentPanelBase\CommandPalette\Livewire\CommandPalette;

beforeEach(function (): void {
    $registry = new CommandPaletteRegistry;
    $registry->register([
        new CommandPaletteAction(id: 'u', label: 'Users', url: '/u'),
        new CommandPaletteAction(id: 'p', label: 'Posts', url: '/p', keywords: ['articles', 'blog']),
        new CommandPaletteAction(id: 's', label: 'Settings', url: '/s'),
        new CommandPaletteAction(id: 'r', label: 'Reports', url: '/r', description: 'Revenue reports'),
    ]);
    app()->instance(CommandPaletteRegistry::class, $registry);

    $this->palette = new CommandPalette;
});

it('returns every action when query is empty', function (): void {
    $this->palette->query = '';

    expect($this->palette->actions)->toHaveCount(4);
});

it('filters by case-insensitive label match', function (): void {
    $this->palette->query = 'POST';

    $matches = $this->palette->actions;
    expect($matches)->toHaveCount(1);
    expect($matches[0]->id)->toBe('p');
});

it('matches against keywords', function (): void {
    $this->palette->query = 'blog';

    $matches = $this->palette->actions;
    expect($matches)->toHaveCount(1);
    expect($matches[0]->id)->toBe('p');
});

it('matches against descriptions', function (): void {
    $this->palette->query = 'revenue';

    $matches = $this->palette->actions;
    expect($matches)->toHaveCount(1);
    expect($matches[0]->id)->toBe('r');
});

it('returns empty array on no matches', function (): void {
    $this->palette->query = 'xyzzy';

    expect($this->palette->actions)->toBe([]);
});

it('ranks label-prefix matches higher than substring matches', function (): void {
    $registry = new CommandPaletteRegistry;
    $registry->register([
        new CommandPaletteAction(id: 'mid', label: 'New User', url: '/n'),    // contains 'user'
        new CommandPaletteAction(id: 'pre', label: 'Users', url: '/u'),       // starts with 'user'
    ]);
    app()->instance(CommandPaletteRegistry::class, $registry);

    $palette = new CommandPalette;
    $palette->query = 'user';

    $matches = $palette->actions;
    expect($matches[0]->id)->toBe('pre');
    expect($matches[1]->id)->toBe('mid');
});

it('groups results under the action group label', function (): void {
    $registry = new CommandPaletteRegistry;
    $registry->register([
        new CommandPaletteAction(id: 'a', label: 'Alpha', url: '/a', group: 'Navigation'),
        new CommandPaletteAction(id: 'b', label: 'Beta', url: '/b', group: 'Navigation'),
        new CommandPaletteAction(id: 'c', label: 'Gamma', url: '/c', group: 'Recent'),
    ]);
    app()->instance(CommandPaletteRegistry::class, $registry);

    $palette = new CommandPalette;
    $groups = $palette->getGroupedActionsProperty();

    expect($groups)->toHaveKey('Navigation');
    expect($groups)->toHaveKey('Recent');
    expect($groups['Navigation'])->toHaveCount(2);
    expect($groups['Recent'])->toHaveCount(1);
});
