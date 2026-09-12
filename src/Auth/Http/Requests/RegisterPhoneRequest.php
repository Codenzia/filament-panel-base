<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Auth\Http\Requests;

use Codenzia\FilamentPanelBase\Auth\Http\Requests\Concerns\NormalizesE164Phone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the start of a phone signup: a single phone field, normalised to
 * E.164 and rejected outright when it is not a valid number (fail closed).
 */
class RegisterPhoneRequest extends FormRequest
{
    use NormalizesE164Phone;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('phone', ''));

        if ($phone !== '') {
            $this->merge(['phone' => $this->normalizeE164($phone) ?? $phone]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'channel' => ['nullable', 'string', 'max:32'],
        ];
    }
}
