<?php

use Codenzia\FilamentPanelBase\CommandPalette\Data\CommandPaletteAction;

it('exposes a searchable haystack including all relevant fields', function (): void {
    $action = new CommandPaletteAction(
        id: 'go:users',
        label: 'Users',
        url: '/admin/users',
        description: 'Manage system users',
        group: 'Navigation',
        keywords: ['accounts', 'people'],
    );

    $haystack = $action->searchHaystack();

    expect($haystack)->toContain('users');
    expect($haystack)->toContain('manage system users');
    expect($haystack)->toContain('navigation');
    expect($haystack)->toContain('accounts');
    expect($haystack)->toContain('people');
});

it('handles missing optional fields without crashing', function (): void {
    $action = new CommandPaletteAction(
        id: 'simple',
        label: 'Just a label',
        url: '/somewhere',
    );

    expect($action->searchHaystack())->toBe('just a label');
});
