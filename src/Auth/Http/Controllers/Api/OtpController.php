<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Controllers\Api;

use Codenzia\FilamentPanelBase\Auth\Contracts\OtpTokenIssuer;
use Codenzia\FilamentPanelBase\Auth\Http\Requests\RequestOtpRequest;
use Codenzia\FilamentPanelBase\Auth\Http\Requests\VerifyOtpRequest;
use Codenzia\FilamentPanelBase\Auth\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Headless OTP endpoints. Thin wrappers over OtpService — no verification
 * logic lives here (generation, hashing, TTL, attempts, replay/consume all
 * stay in OtpService). The OTP code is NEVER returned in a response.
 */
class OtpController
{
    public function __construct(private readonly OtpService $otp) {}

    public function request(RequestOtpRequest $request): JsonResponse
    {
        $target = (string) $request->validated('target');
        $channel = $this->channel($request->validated('channel'));

        try {
            $this->otp->send($target, $channel);
        } catch (\RuntimeException $e) {
            // OtpService throttle (per target+channel) fired.
            return response()->json(['message' => $e->getMessage()], 429);
        }

        return response()->json(['status' => 'sent'], 202);
    }

    public function verify(VerifyOtpRequest $request): JsonResponse
    {
        $target = (string) $request->validated('target');
        $code = (string) $request->validated('code');
        $channel = $this->channel($request->validated('channel'));

        if (! $this->otp->verify($target, $code, $channel)) {
            return response()->json(['verified' => false], 422);
        }

        return response()->json(['verified' => true, ...$this->issueToken($target, $request)], 200);
    }

    private function channel(mixed $channel): ?string
    {
        return is_string($channel) && $channel !== '' ? $channel : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function issueToken(string $target, Request $request): array
    {
        $issuer = config('filament-panel-base.otp_api.token_issuer');

        if ($issuer === null) {
            return [];
        }

        $resolved = is_string($issuer) ? app($issuer) : $issuer;

        if ($resolved instanceof OtpTokenIssuer) {
            return $resolved->issue($target, $request);
        }

        if (is_callable($resolved)) {
            return (array) $resolved($target, $request);
        }

        return [];
    }
}
