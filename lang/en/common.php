<?php

declare(strict_types=1);

return [

    'dashboard' => 'Home',
    'administration' => 'Administration',
    'users' => 'Users',
    'roles' => 'Roles',
    'organization' => 'Organisation',
    'audit_log' => 'Audit log',
    'projects' => 'Projects',

    'save' => 'Save',
    'saved' => 'Saved.',
    'cancel' => 'Cancel',
    'create' => 'Create',
    'edit' => 'Edit',
    'delete' => 'Delete',
    'deactivate' => 'Deactivate',
    'activate' => 'Activate',
    'search' => 'Search',
    'actions' => 'Actions',
    'yes' => 'Yes',
    'no' => 'No',

    'name' => 'Name',
    'email' => 'Email',
    'status' => 'Status',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'position' => 'Position',
    'org_unit' => 'Area',
    'manager' => 'Direct manager',
    'language' => 'Language',
    'timezone' => 'Time zone',
    'preferences' => 'Preferences',

    // Language names always in their own language: someone who landed on an
    // interface they cannot read still recognises theirs in the list.
    'locale_es' => 'Español',
    'locale_en' => 'English',

    // A project's level of detail. "Standard" names the normal case, not the
    // diminished one, and "Specialist" names a craft, not a rank: running a
    // project without critical path is not the beginner edition.
    'detail_level' => 'Level of detail',
    'detail_standard' => 'Standard',
    'detail_specialist' => 'Specialist',
    'detail_help' => 'Standard shows dates, owners and progress. Specialist adds float, constraints and dependency types. Switch whenever you like — nothing is lost on the way down.',

    // Personal preference, which only decides the level new projects you create
    // start at. Each project's own level rules its screens.
    'simple_mode' => 'Standard',
    'expert_mode' => 'Specialist',

    // Empty states: what this is, why it is empty, what to do. Never a blank screen.
    'empty_title' => 'Nothing here yet',
    'empty_users' => 'This is where you manage who can sign in and what each person is allowed to do.',
    'empty_action' => 'Start by creating the first one.',

    'confirm_title' => 'Are you sure?',
    'skip_to_content' => 'Skip to content',

];
