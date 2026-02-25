<?php

use Codenzia\FilamentPanelBase\Concerns\HasContactValidation;

// Create a testable class that uses the trait
class ContactValidationTestClass
{
    use HasContactValidation;

    public function getRules(): array
    {
        return $this->contactValidationRules();
    }

    public function getPreferredContactRules(string $method): array
    {
        return $this->preferredContactValidationRules($method);
    }
}

it('includes required base fields', function () {
    $test = new ContactValidationTestClass;
    $rules = $test->getRules();

    expect($rules)->toHaveKey('name')
        ->and($rules['name'])->toContain('required')
        ->and($rules)->toHaveKey('preferred_contact')
        ->and($rules['preferred_contact'])->toContain('required');
});

it('requires whatsapp when configured', function () {
    config(['filament-panel-base.contact_validation.require_whatsapp' => true]);

    $test = new ContactValidationTestClass;
    $rules = $test->getRules();

    expect($rules['whatsapp'])->toContain('required');
});

it('uses required_without rules when whatsapp not required', function () {
    config(['filament-panel-base.contact_validation.require_whatsapp' => false]);

    $test = new ContactValidationTestClass;
    $rules = $test->getRules();

    expect($rules['email'])->toContain('required_without:whatsapp')
        ->and($rules['whatsapp'])->toContain('required_without:email');
});

it('requires whatsapp for whatsapp preferred contact', function () {
    $test = new ContactValidationTestClass;
    $rules = $test->getPreferredContactRules('whatsapp');

    expect($rules)->toHaveKey('whatsapp')
        ->and($rules['whatsapp'])->toContain('required')
        ->and($rules)->not->toHaveKey('email')
        ->and($rules)->not->toHaveKey('phone');
});

it('requires email for email preferred contact', function () {
    $test = new ContactValidationTestClass;
    $rules = $test->getPreferredContactRules('email');

    expect($rules)->toHaveKey('email')
        ->and($rules['email'])->toContain('required')
        ->and($rules)->not->toHaveKey('whatsapp')
        ->and($rules)->not->toHaveKey('phone');
});

it('requires phone for phone preferred contact', function () {
    $test = new ContactValidationTestClass;
    $rules = $test->getPreferredContactRules('phone');

    expect($rules)->toHaveKey('phone')
        ->and($rules['phone'])->toContain('required')
        ->and($rules)->not->toHaveKey('email')
        ->and($rules)->not->toHaveKey('whatsapp');
});

it('always includes name and preferred_contact in preferred rules', function () {
    $test = new ContactValidationTestClass;

    foreach (['whatsapp', 'email', 'phone'] as $method) {
        $rules = $test->getPreferredContactRules($method);

        expect($rules)->toHaveKey('name')
            ->and($rules)->toHaveKey('preferred_contact');
    }
});
