<?php

declare(strict_types=1);

namespace Codenzia\FilamentPanelBase\Concerns;

/**
 * Shared contact validation rules for lead capture forms.
 *
 * Used by property inquiry, request viewing, and consultation forms.
 */
trait HasContactValidation
{
    /**
     * Get the contact validation rules based on contact settings.
     *
     * @return array<string, string>
     */
    protected function contactValidationRules(): array
    {
        $requireWhatsapp = config('filament-panel-base.contact_validation.require_whatsapp', false);
        $allowEmailAlternative = config('filament-panel-base.contact_validation.allow_email_alternative', true);

        $rules = [
            'name' => 'required|string|max:255',
            'preferred_contact' => 'required|in:whatsapp,email,phone',
            'phone' => 'nullable|string|regex:/^\+?\d{7,15}$/|max:16',
        ];

        if ($requireWhatsapp) {
            $rules['whatsapp'] = 'required|string|regex:/^\+?\d{7,15}$/|max:16';
            $rules['email'] = $allowEmailAlternative
                ? 'nullable|email:rfc|max:255'
                : 'nullable|email:rfc|max:255';
        } else {
            $rules['email'] = 'required_without:whatsapp|nullable|email:rfc|max:255';
            $rules['whatsapp'] = 'required_without:email|nullable|string|regex:/^\+?\d{7,15}$/|max:16';
        }

        return $rules;
    }

    /**
     * Get simplified contact validation rules based on preferred contact method.
     *
     * Used by consultation forms where the required field depends on preferred_contact.
     *
     * @return array<string, string>
     */
    protected function preferredContactValidationRules(string $preferredContact): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'preferred_contact' => 'required|in:whatsapp,email,phone',
        ];

        if ($preferredContact === 'whatsapp') {
            $rules['whatsapp'] = 'required|string|regex:/^\+?\d{7,15}$/|max:16';
        } elseif ($preferredContact === 'email') {
            $rules['email'] = 'required|email:rfc|max:255';
        } else {
            $rules['phone'] = 'required|string|regex:/^\+?\d{7,15}$/|max:16';
        }

        return $rules;
    }
}
