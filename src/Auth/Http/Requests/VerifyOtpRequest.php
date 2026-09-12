<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Requests;

use Codenzia\FilamentPanelBase\Auth\Http\Requests\Concerns\AllowsConfiguredOtpChannels;
use Codenzia\FilamentPanelBase\Auth\Http\Requests\Concerns\NormalizesE164Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an OTP verification request. Applies the same E.164 normalisation
 * as the request endpoint so the submitted target matches the stored code.
 */
class VerifyOtpRequest extends FormRequest
{
    use AllowsConfiguredOtpChannels;
    use NormalizesE164Phone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $target = trim((string) $this->input('target', ''));

        if ($target !== '' && ! str_contains($target, '@')) {
            $this->merge(['target' => $this->normalizeE164($target) ?? $target]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $target = (string) $this->input('target', '');

        $targetRule = str_contains($target, '@')
            ? ['required', 'string', 'email', 'max:255']
            : ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'];

        return [
            'target' => $targetRule,
            'code' => ['required', 'string', 'regex:/^\d{4,8}$/'],
            'channel' => ['nullable', 'string', 'max:32', Rule::in($this->allowedOtpChannels())],
        ];
    }
}
