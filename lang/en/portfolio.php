<?php

declare(strict_types=1);

/*
| The portfolio: every project on one row.
|
| The question it answers is not "how is this project doing" --each project has
| its own board for that-- but "how are we doing", which is what someone with
| more than one project asks, and which until now meant opening twelve screens
| and adding up by hand.
*/

return [

    'title' => 'All projects',
    'help' => 'Worst first. Sorting by name or by date puts the project that needs no attention on the first row, and people open this to find the one that does.',

    'project' => 'Project',
    'tasks' => 'Done',
    'hours' => 'Hours',
    'owner' => 'Owner',
    'weight' => 'How much each project weighs',

    'total_projects' => 'Projects',
    'total_late_projects' => 'Need attention',
    'total_overdue' => 'Overdue tasks',
    'total_alerts' => 'Open alerts',
    'total_cost' => 'Portfolio cost',
    'earned_share' => ':percent % earned against captured progress.',

];
