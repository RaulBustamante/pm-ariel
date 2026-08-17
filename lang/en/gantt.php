<?php

declare(strict_types=1);

return [

    'zoom' => 'Scale',
    'zoom_day' => 'Days',
    'zoom_week' => 'Weeks',
    'zoom_month' => 'Months',

    'today' => 'Today',

    'empty' => 'Nothing to draw yet. Capture tasks in the List view and come back.',

    'legend_task' => 'Task',

    'chart_label' => 'Gantt chart for :project, with :count tasks.',
    'chart_description' => 'Each bar is a task; its length is its duration. Arrows run from a task to the one that depends on it. Red bars are the critical path. Diamonds are milestones. The dashed red line is today. The same information is available as a table in the List view.',

    'reading_help' => 'Grey bands are non-working days: that is why a bar can look like it skips two days. Hover a bar to see its dates.',

    'baseline_bar' => 'Baseline: :from to :to',

    'row_summary' => 'from :from to :to. :state',
    'keyboard_help' => 'Tab moves through the tasks; Enter opens the detail. Each bar dates are readable in the list on the left.',
    'move_dates' => 'Move dates',

    'moved' => ':task was pinned to start no earlier than :date, and the plan was recalculated.',
    'cannot_move_summary' => 'A package cannot be moved: its dates come from the tasks inside it.',
    'drag_confirm' => 'Move this task by :days days? The whole project gets recalculated.',
    'drag_help' => 'You can also drag a bar to move it. Dropping asks before saving.',

];
