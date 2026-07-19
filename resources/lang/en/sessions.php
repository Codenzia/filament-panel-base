<?php

return [
    'tab_label' => 'Devices & Sessions',
    'tab_intro' => 'Every browser and device currently signed in to your account. Revoke any you do not recognize.',

    'driver_required_heading' => 'Session management is unavailable',
    'driver_required_detail' => 'This feature requires SESSION_DRIVER=database. Set it in your .env file and run the sessions table migration to enable it.',

    'empty' => 'No active sessions found.',

    'this_device' => 'Current',
    'unknown_ip' => 'Unknown IP',
    'active_now' => 'Active now',
    'last_active' => 'Last active :time',

    'revoke' => 'Revoke',
    'sign_out' => 'Sign out',
    'revoke_confirm' => 'Sign this device out of your account?',
    'revoke_current_confirm' => 'This will sign you out of this device. Continue?',
    'revoked_notification' => 'Session revoked.',

    'logout_others_button' => 'Sign out everywhere else',
    'logout_others_confirm' => 'Sign out of every other device except this one?',
    'logout_others_notification' => ':count session(s) revoked.',

    'confirm_password_intro' => 'Enter your current password to confirm this action.',
    'confirm_password_label' => 'Current password',
    'password_incorrect' => 'The password you entered is incorrect.',
    'confirm' => 'Confirm',
    'cancel' => 'Cancel',
];
