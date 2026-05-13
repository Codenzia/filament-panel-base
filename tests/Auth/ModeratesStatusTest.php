<?php

use Codenzia\FilamentPanelBase\Auth\Concerns\ModeratesStatus;
use Codenzia\FilamentPanelBase\Auth\Events\ModerationApproved;
use Codenzia\FilamentPanelBase\Auth\Events\ModerationSuspended;
use Codenzia\FilamentPanelBase\Contracts\HasModerationStatus;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Event;

/**
 * @property string $status
 */
class _ModeratesStatusFakeUser extends Authenticatable implements HasModerationStatus
{
    use ModeratesStatus;

    protected $table = 'users';

    protected $guarded = [];

    public string $status = 'pending';

    // Stub Eloquent's persistence — we only want the in-memory state.
    public function save(array $options = []): bool
    {
        return true;
    }
}

it('reports status correctly via the helper methods', function (): void {
    $user = new _ModeratesStatusFakeUser;
    $user->status = 'pending';

    expect($user->isPending())->toBeTrue()
        ->and($user->isApproved())->toBeFalse()
        ->and($user->isSuspended())->toBeFalse();

    $user->status = 'approved';

    expect($user->isApproved())->toBeTrue()
        ->and($user->isPending())->toBeFalse();

    $user->status = 'suspended';

    expect($user->isSuspended())->toBeTrue();
});

it('fires ModerationApproved on transition to approved', function (): void {
    Event::fake([ModerationApproved::class]);

    $user = new _ModeratesStatusFakeUser;
    $user->status = 'pending';

    $user->approve();

    Event::assertDispatched(ModerationApproved::class);
});

it('fires ModerationSuspended on transition to suspended', function (): void {
    Event::fake([ModerationSuspended::class]);

    $user = new _ModeratesStatusFakeUser;
    $user->status = 'approved';

    $user->suspend('spam');

    Event::assertDispatched(ModerationSuspended::class, function (ModerationSuspended $event): bool {
        return $event->reason === 'spam';
    });
});

it('does not refire ModerationApproved when user is already approved', function (): void {
    Event::fake([ModerationApproved::class]);

    $user = new _ModeratesStatusFakeUser;
    $user->status = 'approved';

    $user->approve();

    Event::assertNotDispatched(ModerationApproved::class);
});
