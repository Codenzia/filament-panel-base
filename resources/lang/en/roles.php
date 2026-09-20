<?php

declare(strict_types=1);

/*
 * Display names for the role slugs the fleet's panels share, used by the user
 * menu's role chip. An app that ships its own `roles.<name>` entry, or a role
 * model with its own getLabel(), overrides anything here; a slug with no entry
 * is printed as stored.
 */
return [
    'super_admin' => 'Super admin',
    'admin' => 'Admin',
    'manager' => 'Manager',
    'moderator' => 'Moderator',
    'owner' => 'Owner',
    'staff' => 'Staff',
    'vendor' => 'Vendor',
    'customer' => 'Customer',
    'user' => 'User',
];
