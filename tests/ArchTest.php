<?php

it('will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

it('contracts are interfaces')
    ->expect('Codenzia\FilamentPanelBase\Contracts')
    ->toBeInterfaces();

it('middleware classes are not abstract')
    ->expect('Codenzia\FilamentPanelBase\Middleware')
    ->not->toBeAbstract();
