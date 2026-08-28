<?php

declare(strict_types=1);

return [

    'title' => 'Users',
    'create_title' => 'New user',
    'edit_title' => 'Edit user',
    'create_action' => 'Create user',

    'roles' => 'Roles',
    'roles_help' => 'A role groups permissions. Costs are a separate permission: a manager can see their team progress without seeing rates.',

    'created_with_password' => 'User :email created. Temporary password: :password — write it down now; it is not shown again and must be changed at first sign-in.',
    'updated' => 'User updated.',

    'password_title' => 'Password',
    'password_help' => 'For when someone forgot it and the recovery email is not an option. The current one cannot be looked up: it is not stored anywhere, only its hash.',
    'password_new' => 'New password',
    'password_confirm' => 'Repeat the password',
    'password_force_change' => 'Require a change at first sign-in',
    'password_force_change_help' => 'While the one you set is still valid, two people know the account.',
    'password_set_action' => 'Save password',
    'password_set' => 'Password for :email updated.',
    'password_reset_action' => 'Generate temporary',
    'password_reset_help' => 'Generates a random password and shows it once. It always requires a change at sign-in.',
    'password_reset_confirm' => 'Generate a new password? The current one stops working immediately.',
    'password_reset_with_password' => 'Password for :email reset. New temporary password: :password — write it down now; it is not shown again.',
    'password_not_managed' => 'Account :email is managed by the identity provider: its password is not changed from here.',

    'no_roles' => 'No roles',
    'never_signed_in' => 'Never signed in',

];
