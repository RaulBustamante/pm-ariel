<?php

declare(strict_types=1);

return [

    'title' => 'Hierarchy',
    'heading' => 'Who reports to whom',
    'intro' => 'This is more than an org chart: a manager sees every project down their chain. Changing a manager changes what that person can reach.',

    'person' => 'Person',
    'manager' => 'Direct manager',
    'no_manager' => 'No manager (reports to nobody)',
    'change' => 'Change manager',
    'assign_heading' => 'Direct manager of :name',

    'current' => 'Current manager',
    'none' => '—',

    'roots' => 'No manager assigned',
    'roots_help' => 'Usually just one person: the head of the organization. If several show up here, check whether someone is missing a manager.',

    'updated' => 'Manager updated for :name.',
    'would_create_cycle' => 'That change would place the person above their own manager. Review the chain before moving them.',
    'cannot_manage_self' => 'Nobody can be their own manager.',

    'history_note' => 'The change closes the previous relation with today as its end date; it does not delete it. That history is what later explains why someone could see a given project.',

];
