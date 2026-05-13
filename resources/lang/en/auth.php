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
    'phone_invalid' => 'The :attribute is not a valid phone number.',
    'phone_format_invalid' => 'The :attribute format is invalid. Use international format, e.g. +14155552671.',
    'throttle_rate_limited' => 'Too many attempts. Try again in :seconds seconds.',
];
