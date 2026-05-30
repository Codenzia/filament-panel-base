<?php

use Codenzia\FilamentPanelBase\CommandPalette\Models\RecentView;
use Codenzia\FilamentPanelBase\CommandPalette\Services\RecentViewRecorder;
use Codenzia\FilamentPanelBase\CommandPalette\Settings\CommandPaletteSettings;
use Codenzia\FilamentPanelBase\Tests\Support\TwoFactorUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

afterEach(function (): void {
    Carbon::setTestNow(null);
});

beforeEach(function (): void {
    $this->createUsersTable();

    Schema::create('command_palette_recent_views', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('panel', 40)->nullable()->index();
        $table->string('resource_class', 255);
        $table->string('record_id', 64);
        $table->string('label', 255);
        $table->string('url', 2048);
        $table->timestamp('viewed_at')->index();
        $table->timestamps();
        $table->unique(['user_id', 'panel', 'resource_class', 'record_id'], 'cp_recent_views_unique');
    });

    $this->settings = $this->settingsStub(CommandPaletteSettings::class);
    $this->settings->enabled = true;
    $this->settings->track_recent_views = true;
    $this->settings->recent_view_limit = 3;
    app()->instance(CommandPaletteSettings::class, $this->settings);

    $this->recorder = new RecentViewRecorder($this->settings);

    $this->user = TwoFactorUser::create(['email' => 'a@b.com', 'password' => 'x']);
    Auth::setUser($this->user);
});

it('creates a row when a record is viewed', function (): void {
    $record = TwoFactorUser::create(['email' => 'other@b.com', 'password' => 'x']);

    $this->recorder->record(
        resourceClass: TwoFactorUser::class,
        record: $record,
        url: 'https://example.com/admin/users/'.$record->id,
        label: 'Other User',
        panelId: 'admin',
    );

    expect(RecentView::count())->toBe(1);

    $row = RecentView::first();
    expect($row->user_id)->toBe($this->user->id);
    expect($row->panel)->toBe('admin');
    expect($row->label)->toBe('Other User');
    expect($row->record_id)->toBe((string) $record->id);
});

it('updates viewed_at instead of inserting on the same key', function (): void {
    $record = TwoFactorUser::create(['email' => 'other@b.com', 'password' => 'x']);

    $this->recorder->record(TwoFactorUser::class, $record, '/url', 'Label', 'admin');
    $firstId = RecentView::first()->id;

    Carbon::setTestNow(now()->addMinutes(2));

    $this->recorder->record(TwoFactorUser::class, $record, '/url', 'Updated Label', 'admin');

    expect(RecentView::count())->toBe(1);
    $row = RecentView::first();
    expect($row->id)->toBe($firstId);
    expect($row->label)->toBe('Updated Label');
});

it('prunes anything beyond the configured limit', function (): void {
    for ($i = 0; $i < 7; $i++) {
        Carbon::setTestNow(now()->addSeconds(1));
        $record = TwoFactorUser::create(['email' => "user{$i}@b.com", 'password' => 'x']);
        $this->recorder->record(TwoFactorUser::class, $record, "/u{$i}", "User {$i}", 'admin');
    }

    expect(RecentView::where('user_id', $this->user->id)->where('panel', 'admin')->count())->toBe(3);
});

it('does nothing when the module is disabled', function (): void {
    $this->settings->enabled = false;
    $record = TwoFactorUser::create(['email' => 'other@b.com', 'password' => 'x']);

    $this->recorder->record(TwoFactorUser::class, $record, '/u', 'Label', 'admin');

    expect(RecentView::count())->toBe(0);
});

it('does nothing when track_recent_views is off', function (): void {
    $this->settings->track_recent_views = false;
    $record = TwoFactorUser::create(['email' => 'other@b.com', 'password' => 'x']);

    $this->recorder->record(TwoFactorUser::class, $record, '/u', 'Label', 'admin');

    expect(RecentView::count())->toBe(0);
});

it('does nothing when no user is authenticated', function (): void {
    Auth::logout();
    $record = TwoFactorUser::create(['email' => 'other@b.com', 'password' => 'x']);

    $this->recorder->record(TwoFactorUser::class, $record, '/u', 'Label', 'admin');

    expect(RecentView::count())->toBe(0);
});

it('truncates oversize labels and URLs', function (): void {
    $record = TwoFactorUser::create(['email' => 'other@b.com', 'password' => 'x']);
    $longLabel = str_repeat('a', 1000);
    $longUrl = 'https://example.com/'.str_repeat('b', 3000);

    $this->recorder->record(TwoFactorUser::class, $record, $longUrl, $longLabel, 'admin');

    $row = RecentView::first();
    expect(mb_strlen($row->label))->toBeLessThanOrEqual(255);
    expect(mb_strlen($row->url))->toBeLessThanOrEqual(2048);
});
