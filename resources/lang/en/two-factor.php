<?php

return [
    'tab_label' => 'Two-Factor Authentication',
    'tab_intro_disabled' => 'Add a second step to your sign-in by using a TOTP code from an authenticator app on your phone.',
    'tab_intro_apps' => 'Compatible apps include Google Authenticator, 1Password, Authy, and Microsoft Authenticator.',

    'enable_button' => 'Enable two-factor authentication',
    'enable_description' => 'A QR code and recovery codes will be generated. You will then need to verify a code from your authenticator app to finish setup.',
    'enable_notification' => 'Scan the QR code with your authenticator app, then enter a code to confirm.',

    'pending_intro' => 'Scan the QR code with your authenticator app, then enter the 6-digit code to confirm enrolment.',
    'manual_key_label' => 'Or enter this key manually:',

    'confirm_button' => 'Confirm code',
    'confirmation_code_label' => 'Authenticator code',
    'confirmed_notification' => 'Two-factor authentication is now active on your account.',

    'invalid_code' => 'That code is not valid. Try again or use a recovery code.',
    'unavailable' => 'Two-factor authentication is not configured for this account.',

    'disable_button' => 'Disable two-factor',
    'disable_description' => 'You will be able to sign in with just your password again. Your recovery codes will be discarded.',
    'disabled_notification' => 'Two-factor authentication has been turned off.',

    'regenerate_button' => 'Regenerate recovery codes',
    'recovery_regenerated' => 'A fresh set of recovery codes has been generated. Save them now — the old ones no longer work.',

    'recovery_codes_heading' => 'Recovery codes',
    'recovery_codes_warning' => 'Store these somewhere safe — they are shown only once. Each code can be used a single time to sign in if you lose your authenticator app.',

    'enabled_status' => 'Two-factor authentication is enabled.',
    'enabled_status_detail' => 'You have :count recovery codes remaining.',

    'challenge_title' => 'Two-factor authentication',
    'challenge_intro' => 'Enter the code from your authenticator app, or a one-time recovery code, to finish signing in.',
    'code_label' => 'Authenticator or recovery code',
    'code_hint' => 'Codes refresh every 30 seconds.',
    'submit' => 'Verify and sign in',
    'remember_device' => 'Trust this device for 30 days',

    'user_trait_missing' => 'The HasTwoFactorAuthentication trait has not been added to your User model. Add it and run migrate to enable this feature.',
    'enrolment_required' => 'Your role requires two-factor authentication. Please enrol before continuing.',
];
