<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Models\DemoSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('demo_settings', function ($table) {
        $table->id();
        $table->text('password')->nullable();
        $table->timestamp('rotated_at')->nullable();
        $table->timestamp('last_used_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('demo_settings');
});

it('encrypts the password column at rest', function () {
    $row = DemoSetting::current();
    $row->password = 'plain-secret-value';
    $row->save();

    // Raw DB value should be encrypted (Crypter-prefixed payload), not the
    // plaintext.
    $raw = DB::table('demo_settings')->where('id', 1)->value('password');
    expect($raw)->not->toBeNull()
        ->and($raw)->not->toBe('plain-secret-value')
        ->and(strlen((string) $raw))->toBeGreaterThan(strlen('plain-secret-value'));

    // Eloquent accessor decrypts on read.
    $row->refresh();
    expect($row->password)->toBe('plain-secret-value');
});

it('current() returns a singleton row with id=1', function () {
    expect(DB::table('demo_settings')->count())->toBe(0);

    $a = DemoSetting::current();
    expect($a->id)->toBe(1);
    expect(DB::table('demo_settings')->count())->toBe(1);

    // Calling again must NOT create a second row.
    $b = DemoSetting::current();
    expect($b->id)->toBe(1);
    expect(DB::table('demo_settings')->count())->toBe(1);
});

it('current() returns the same persisted row across calls after save', function () {
    $row = DemoSetting::current();
    $row->password = 'first';
    $row->rotated_at = now();
    $row->save();

    $reloaded = DemoSetting::current();
    expect($reloaded->id)->toBe(1)
        ->and($reloaded->password)->toBe('first')
        ->and($reloaded->rotated_at)->not->toBeNull();
});

it('casts rotated_at and last_used_at to Carbon', function () {
    $row = DemoSetting::current();
    $row->rotated_at = '2026-05-20 10:00:00';
    $row->last_used_at = '2026-05-20 11:00:00';
    $row->save();
    $row->refresh();

    expect($row->rotated_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
        ->and($row->last_used_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('allows nullable password (gate disabled when no DB value and no env)', function () {
    $row = DemoSetting::current();
    expect($row->password)->toBeNull();
});
