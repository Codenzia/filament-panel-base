<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Contracts;

use Codenzia\FilamentPanelBase\Auth\Concerns\HasPhoneNumber;
use Illuminate\Support\Carbon;

/**
 * User models opt into phone-based credentials / phone OTP verification by
 * implementing this contract. The default trait implementation lives in
 * {@see HasPhoneNumber}.
 */
interface HasPhone
{
    public function getPhone(): ?string;

    public function getPhoneVerifiedAt(): ?Carbon;

    public function hasVerifiedPhone(): bool;

    public function markPhoneVerified(): bool;
}
