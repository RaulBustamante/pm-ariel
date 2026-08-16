<?php

declare(strict_types=1);

return [

    'title' => 'Areas',
    'heading' => 'Organization areas',
    'intro' => 'The area tree defines which part of the organization each person belongs to. It is used to group projects and reports.',

    'new' => 'New area',
    'edit' => 'Edit area',

    'name' => 'Name',
    'code' => 'Code',
    'code_help' => 'Optional, short and unique. Used for reports and exports.',
    'parent' => 'Reports to',
    'no_parent' => 'None (top level)',
    'sort_order' => 'Order',
    'sort_order_help' => 'Among sibling areas, lowest first. Ties are broken by name.',
    'people' => 'People',
    'level' => 'Level',

    'created' => 'Area created.',
    'updated' => 'Area updated.',
    'deleted' => 'Area deleted.',

    'cannot_be_its_own_parent' => 'An area cannot report to itself.',
    'cannot_move_under_descendant' => 'An area cannot be moved under one that already sits below it: both would fall out of the tree.',
    'has_users' => 'This area has people assigned. Move them to another area before deleting it.',
    'has_children' => 'This area has areas below it. Move or delete those first.',

    'empty' => 'No areas yet. Start with the top-level one, usually the company or the division.',

    'confirm_delete' => 'Delete this area?',

];
