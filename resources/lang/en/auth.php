<?php

return [
    // Generic
    'name' => 'Name',
    'email' => 'Email',
    'phone' => 'Phone number',
    'password' => 'Password',
    'password_confirmation' => 'Confirm password',
    'remember_me' => 'Remember me',
    'submit' => 'Submit',
    'cancel' => 'Cancel',

    // Register
    'register_title' => 'Create an account',
    'register_subtitle' => 'Get started by creating your account.',
    'register_submit' => 'Create account',
    'already_have_account' => 'Already have an account?',
    'sign_in' => 'Sign in',

    // Session expiry (419)
    'session_expired' => 'Your session expired. Please log in again.',

    // Login
    'login_title' => 'Welcome back',
    'login_subtitle' => 'Sign in to continue.',
    'login_submit' => 'Sign in',
    'forgot_password' => 'Forgot your password?',
    'no_account_yet' => 'No account yet?',
    'create_account' => 'Create one',
    'credentials_mismatch' => 'These credentials do not match our records.',
    'account_suspended' => 'Your account has been suspended. Please contact support.',
    'account_pending' => 'Your account is awaiting approval. You will be notified once approved.',

    // Identifier label (varies with credentials_mode)
    'identifier_email' => 'Email address',
    'identifier_phone' => 'Phone number',
    'identifier_either' => 'Email or phone',

    // Email verification
    'verify_email_title' => 'Verify your email',
    'verify_email_intro' => "We've sent a verification link to :email. Open the link to activate your account.",
    'verify_email_resend' => 'Resend verification link',
    'verify_email_resent' => 'A new verification link has been sent.',
    'verify_email_done' => 'Your email is already verified.',

    // OTP verification
    'verify_otp_title' => 'Verify your :channel',
    'verify_otp_intro' => 'Enter the :length-digit code we sent to :target.',
    'verify_otp_submit' => 'Verify',
    'verify_otp_resend' => 'Resend code',
    'verify_otp_resent' => 'A new code has been sent.',
    'verify_otp_invalid' => 'The verification code is invalid or has expired.',
    'verify_otp_rate_limited' => 'Too many attempts. Try again in :seconds seconds.',

    // OTP delivery
    'otp_email_subject' => 'Your :brand verification code',
    'otp_email_greeting' => 'Hello,',
    'otp_email_intro' => 'Use the code below to complete your sign-in or registration on :brand.',
    'otp_email_ttl' => 'This code expires in :minutes minutes.',
    'otp_email_ignore' => 'If you did not request this code, you can safely ignore this email.',
    'otp_sms_body' => 'Your :brand verification code is :code',
    'otp_rate_limited' => 'Please wait :seconds seconds before requesting another code.',

    // Forgot / reset password
    'forgot_title' => 'Reset your password',
    'forgot_subtitle' => "Enter your email and we'll send a reset link.",
    'forgot_submit' => 'Send reset link',
    'forgot_sent' => 'If that email is in our system, a reset link has been sent.',
    'reset_title' => 'Choose a new password',
    'reset_submit' => 'Update password',
    'reset_done' => 'Your password has been updated. You can now sign in.',

    // Social
    'continue_with' => 'Continue with :provider',
    'or_continue_with' => 'Or continue with',
    'social_link_existing_email' => 'An account with this email already exists. Sign in with your original method, then connect :provider from your profile.',
    'social_missing_email' => 'Your :provider account did not share an email address. Sign up with email instead, or contact support.',
    'social_link_owned_by_other' => 'This :provider account is already linked to a different user. Disconnect it from that account first.',
    'social_link_success' => ':provider has been connected to your account.',
    'oauth_invalid_state' => 'Your sign-in session expired. Please try again.',
    'oauth_provider_error' => 'Sign in with :provider failed. Please try again or use another method.',

    // Manage connected accounts
    'social_manage_title' => 'Connected accounts',
    'social_manage_subtitle' => 'Link providers so you can sign in faster, or disconnect ones you no longer use.',
    'social_connected' => 'Connected',
    'social_available' => 'Available',
    'social_connect' => 'Connect :provider',
    'social_disconnect' => 'Disconnect',
    'social_disconnect_locked' => 'Only sign-in method',
    'social_disconnect_confirm' => 'Disconnect :provider from your account?',
    'social_disconnect_success' => ':provider has been disconnected.',
    'social_disconnect_blocked' => 'Set a password before disconnecting your only sign-in method.',
    'social_none_configured' => 'No social providers are enabled for this site.',

    // Channel labels (for OTP UI)
    'channel.email' => 'email',
    'channel.whatsapp' => 'WhatsApp',
    'channel.twilio' => 'SMS',
    'channel.vonage' => 'SMS',
    'channel.null' => 'log',

    // Moderation
    'moderation_approved_subject' => 'Your :brand account has been approved',
    'moderation_approved_body' => 'Welcome to :brand! Your account is now active. Sign in to get started.',
    'moderation_suspended_subject' => 'Your :brand account has been suspended',
    'moderation_suspended_body' => 'Your account has been suspended. Reason: :reason',

    // Validation
    'email_disposable' => 'The :attribute domain is not allowed. Please use a permanent email address.',
    'email_domain_not_allowed' => 'Sign-ups are restricted to approved email domains. Please use your organisation email.',
    'phone_invalid' => 'The :attribute is not a valid phone number.',
    'phone_format_invalid' => 'The :attribute format is invalid. Use international format, e.g. +14155552671.',
    'throttle_rate_limited' => 'Too many attempts. Try again in :seconds seconds.',

    // Manage Authentication Settings page
    'settings_nav_label' => 'Authentication',
    'settings_nav_group' => 'Settings',
    'settings_title' => 'Authentication settings',
    'settings_save' => 'Save changes',
    'settings_saved' => 'Authentication settings saved.',
    'settings_unknown_otp_drivers' => 'Unknown OTP drivers were ignored: :drivers',

    'settings_section_registration' => 'Registration & sign-in',
    'settings_section_registration_description' => 'Control how new users join and what identifier they sign in with.',
    'settings_registration_mode' => 'Registration mode',
    'settings_registration_mode_open' => 'Open — anyone can register',
    'settings_registration_mode_moderated' => 'Moderated — admin must approve',
    'settings_registration_mode_help' => 'Moderated mode places new users in a pending state until an admin approves them.',
    'settings_credentials_mode' => 'Identifier',
    'settings_credentials_mode_email' => 'Email only',
    'settings_credentials_mode_phone' => 'Phone only',
    'settings_credentials_mode_both' => 'Email or phone',
    'settings_credentials_mode_help' => 'Determines which field users sign in with and what the registration form collects.',
    'settings_phone_required' => 'Phone number is required',
    'settings_phone_required_help' => 'When enabled and the identifier is "Email or phone", a phone is still required at signup.',
    'settings_default_country_code' => 'Default country code',
    'settings_disposable_email_blocking' => 'Block disposable email domains',
    'settings_disposable_email_blocking_help' => 'Reject sign-ups using throwaway email providers (e.g. 10minutemail).',
    'settings_allowed_email_domains' => 'Restrict sign-ups to these email domains',
    'settings_allowed_email_domains_help' => 'Leave empty to allow any domain. Add e.g. "acme.com" to permit only that domain and its subdomains. Combines with the registration mode above.',

    'settings_section_verification' => 'Verification',
    'settings_section_verification_description' => 'Decide which channels must be verified before granting access.',
    'settings_require_email_verification' => 'Require email verification',
    'settings_require_phone_verification' => 'Require phone verification',

    'settings_section_otp' => 'One-time codes (OTP)',
    'settings_section_otp_description' => 'Driver and lifetime for verification codes sent during signup and login.',
    'settings_otp_driver' => 'Active OTP driver',
    'settings_allowed_otp_drivers' => 'Allowed OTP drivers',
    'settings_allowed_otp_drivers_help' => 'Drivers admins may switch to. Add or remove via the tags input.',
    'settings_otp_code_length' => 'Code length (digits)',
    'settings_otp_ttl_minutes' => 'Code lifetime (minutes)',

    'settings_section_social' => 'Social login',
    'settings_section_social_description' => 'OAuth providers that appear on the login and registration pages.',
    'settings_social_providers_enabled' => 'Enabled providers',
    'settings_social_providers_enabled_help' => 'Provider keys (lowercase, e.g. "google", "github"). Each one must have credentials in config/services.php.',
    'settings_social_email_linking' => 'Email-conflict policy',
    'settings_social_email_linking_require_login' => 'Require login first (safest)',
    'settings_social_email_linking_trust_verified' => 'Auto-link when both sides are verified',
    'settings_social_email_linking_auto' => 'Auto-link by email match (unsafe — only the provider-verified email is linked)',
    'settings_social_email_linking_help' => 'What to do when a social sign-in returns an email that already belongs to a local account.',
    'settings_social_trust_verified_email' => 'Trust provider-verified email',
    'settings_social_trust_verified_email_help' => 'When the provider reports the email as verified, skip the in-app email verification flow.',

    'settings_section_throttle' => 'Rate limiting',
    'settings_section_throttle_description' => 'Brute-force protection for sign-in, signup, OTP and password-reset flows.',
    'settings_throttle_per_minute' => 'Attempts per minute',
    'settings_throttle_per_day' => 'Attempts per day',
];
