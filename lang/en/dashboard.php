<?php

declare(strict_types=1);

return [

    'title' => 'Dashboard',

    'progress' => 'Progress',
    'progress_help' => 'Weighted by duration, not by task count.',
    'elapsed' => 'Time elapsed',
    'elapsed_help' => 'From start to calculated finish.',
    'behind_by' => 'Progress is :points points below elapsed time.',
    'overdue' => 'Overdue',
    'overdue_help' => 'Should have finished and are not at 100 %.',
    'finish' => 'Calculated finish',

    'light_green' => 'The project is on schedule.',
    'light_amber' => 'There are things worth reviewing.',
    'light_red' => 'Something threatens delivery.',

    'why_green' => 'Nothing overdue and no open alerts.',
    'why_overdue' => 'There are :count task(s) that should have finished and are still open.',
    'why_behind' => 'Progress is at :progress % and elapsed time at :elapsed %.',
    'why_amber_generic' => 'There are open alerts in the Alerts panel.',

    's_curve' => 'S-curve — cumulative progress',
    's_curve_label' => 'Cumulative progress curve: planned against actual.',
    's_curve_description' => 'The grey dashed line is the work that should be finished each week according to the plan. The blue line is what is actually finished, and it stops at the current week. Where they diverge is where the project is slipping. The same data is in the List view, task by task.',
    's_curve_help' => 'The actual line stops at today: drawing it into the future would claim progress that has not happened.',
    'planned' => 'Planned',
    'actual' => 'Actual',

    'distribution' => 'Task distribution',
    'no_data' => 'No tasks with dates yet.',

];
