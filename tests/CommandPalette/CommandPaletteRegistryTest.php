<?php

use Codenzia\FilamentPanelBase\CommandPalette\CommandPaletteRegistry;
use Codenzia\FilamentPanelBase\CommandPalette\Contracts\CommandPaletteContributor;
use Codenzia\FilamentPanelBase\CommandPalette\Data\CommandPaletteAction;

class StubContributor implements CommandPaletteContributor
{
    /** @param array<int, CommandPaletteAction> $actions */
    public function __construct(public array $actions) {}

    public function actions(?string $query = null): iterable
    {
        return $this->actions;
    }
}

beforeEach(function (): void {
    $this->registry = new CommandPaletteRegistry;
});

it('starts empty', function (): void {
    expect($this->registry->collect())->toBe([]);
});

it('collects actions from a Contributor', function (): void {
    $a = new CommandPaletteAction(id: 'a', label: 'Alpha', url: '/a');
    $b = new CommandPaletteAction(id: 'b', label: 'Beta', url: '/b');

    $this->registry->register(new StubContributor([$a, $b]));

    expect($this->registry->collect())->toHaveCount(2);
});

it('collects actions from a callable contributor', function (): void {
    $this->registry->register(fn () => [
        new CommandPaletteAction(id: 'c', label: 'Gamma', url: '/c'),
    ]);

    $result = $this->registry->collect();

    expect($result)->toHaveCount(1);
    expect($result[0]->id)->toBe('c');
});

it('collects actions from a raw array contributor', function (): void {
    $this->registry->register([
        new CommandPaletteAction(id: 'd', label: 'Delta', url: '/d'),
    ]);

    expect($this->registry->collect())->toHaveCount(1);
});

it('dedupes contributors that emit the same id', function (): void {
    $this->registry->register(new StubContributor([
        new CommandPaletteAction(id: 'shared', label: 'First', url: '/1'),
    ]));
    $this->registry->register(new StubContributor([
        new CommandPaletteAction(id: 'shared', label: 'Second', url: '/2'),
        new CommandPaletteAction(id: 'unique', label: 'Other', url: '/o'),
    ]));

    $collected = $this->registry->collect();

    expect($collected)->toHaveCount(2);
    expect($collected[0]->label)->toBe('First'); // first wins
});

it('respects the limit cap', function (): void {
    $actions = [];
    for ($i = 0; $i < 20; $i++) {
        $actions[] = new CommandPaletteAction(id: "a-{$i}", label: "Action {$i}", url: "/{$i}");
    }
    $this->registry->register(new StubContributor($actions));

    expect($this->registry->collect(limit: 5))->toHaveCount(5);
});

it('swallows contributor exceptions and continues', function (): void {
    $this->registry->register(function () {
        throw new RuntimeException('contributor blew up');
    });
    $this->registry->register(new StubContributor([
        new CommandPaletteAction(id: 'survives', label: 'Survives', url: '/s'),
    ]));

    $result = $this->registry->collect();

    expect($result)->toHaveCount(1);
    expect($result[0]->id)->toBe('survives');
});

it('passes the query to contributors that accept it', function (): void {
    $received = null;
    $this->registry->register(function (?string $q) use (&$received) {
        $received = $q;

        return [];
    });

    $this->registry->collect('search-term');

    expect($received)->toBe('search-term');
});
