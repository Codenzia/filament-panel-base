<?php

declare(strict_types=1);

use Codenzia\FilamentPanelBase\Auth\Drivers\Otp\EmailOtpDriver;
use Codenzia\FilamentPanelBase\Auth\Notifications\OtpCodeNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Queue;

it('is not a queued notification so the cleartext code never enters a job payload (PNB-036)', function (): void {
    // Queueing the notification serialises the raw OTP into the job payload,
    // which then sits in the jobs table / redis in the clear. The class must
    // NOT implement ShouldQueue.
    $reflection = new ReflectionClass(OtpCodeNotification::class);

    expect($reflection->implementsInterface(ShouldQueue::class))->toBeFalse();
});

it('sends the OTP synchronously without pushing a job carrying the code (PNB-036)', function (): void {
    config()->set('mail.default', 'array');
    Queue::fake();

    (new EmailOtpDriver)->send('user@example.com', '123456', ['brand' => 'Acme']);

    // Nothing hits the queue — the raw code is delivered inline and only ever
    // lives in memory, never in a persisted, replayable job payload.
    Queue::assertNothingPushed();
});
