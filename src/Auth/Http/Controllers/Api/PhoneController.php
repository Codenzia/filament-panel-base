<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Controllers\Api;

use Codenzia\FilamentPanelBase\Auth\Http\Requests\RegisterPhoneRequest;
use Codenzia\FilamentPanelBase\Auth\Services\OtpService;
use Illuminate\Http\JsonResponse;

/**
 * Begins a phone signup: validates + normalises the phone to E.164 and issues
 * an OTP through OtpService. The host app creates/links the user in its
 * OtpTokenIssuer (or an OtpVerified listener) once the code is verified.
 */
class PhoneController
{
    public function __construct(private readonly OtpService $otp) {}

    public function register(RegisterPhoneRequest $request): JsonResponse
    {
        $phone = (string) $request->validated('phone');
        $channelInput = $request->validated('channel');
        $channel = is_string($channelInput) && $channelInput !== '' ? $channelInput : null;

        try {
            $this->otp->send($phone, $channel);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 429);
        }

        return response()->json(['status' => 'sent', 'target' => $phone], 202);
    }
}
