<?php

declare(strict_types=1);

return [

    'title' => 'Positions',
    'intro' => 'The positions in the organisation. Level orders them from highest to lowest and is used to present the list and to know who to escalate to. It grants no permissions: roles do that.',
    'create' => 'New position',
    'edit' => 'Edit position',
    'name' => 'Position name',
    'name_help' => 'What it is called at Ariel. For example: Systems Manager.',
    'level' => 'Level',
    'level_help' => '1 is the top of the organisation. It only orders; it grants no access.',
    'people' => 'People',
    'created' => 'Position created.',
    'updated' => 'Position updated.',
    'deleted' => 'Position deleted.',
    'in_use' => 'Cannot delete: :count person(s) hold this position. Move them first.',
    'confirm_delete' => 'Delete this position?',
    'empty_title' => 'No positions yet',
    'empty_body' => 'Creating a user asks for a position and the list stays empty until the first one exists. You can load a starter catalogue with: artisan db:seed --class=PositionsSeeder',
];
