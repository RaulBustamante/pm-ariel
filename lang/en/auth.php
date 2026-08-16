<?php

declare(strict_types=1);

return [

    // One message for wrong credentials, unknown account and disabled account:
    // telling them apart confirms to an attacker which addresses exist.
    'failed' => 'We could not sign you in with those details. Check the email and password.',
    'password' => 'That password is not correct.',
    'throttle' => 'Too many attempts. Try again in :seconds seconds.',

    'sign_in' => 'Sign in',
    'sign_out' => 'Sign out',
    'email' => 'Email address',
    'password_label' => 'Password',
    'remember_me' => 'Keep me signed in',
    'forgot_password' => 'Forgot your password?',

    'change_password_title' => 'Change your password',
    'change_password_intro' => 'The password your administrator gave you is temporary. While it stands, two people know your account.',
    'current_password' => 'Current password',
    'new_password' => 'New password',
    'confirm_password' => 'Confirm the new password',
    'password_rules' => 'At least 10 characters, with letters and numbers.',
    'password_updated' => 'Your password has been updated.',
    'password_must_differ' => 'The new password must differ from the current one.',

    'reset_title' => 'Recover access',
    'reset_intro' => 'Enter your email and we will send a link to create a new password.',
    'reset_send_link' => 'Send link',
    'reset_new_title' => 'Create your new password',
    'reset_submit' => 'Save password',
    'back_to_login' => 'Back to sign in',

];
