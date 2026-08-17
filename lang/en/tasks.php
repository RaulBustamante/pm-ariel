<?php

declare(strict_types=1);

return [

    'title' => 'Work plan',
    'list_view' => 'List',
    'gantt_view' => 'Gantt',

    'intro' => 'Capture the tasks, how long they take and what they depend on. Dates are recalculated every time you change something.',

    'add' => 'Add task',
    'new_task' => 'New task',
    'name' => 'Task',
    'duration' => 'Duration',
    'duration_help' => 'Write it however you like: 3d, 4h, 30m, 2w. Leave it at 0 for a milestone.',
    'predecessors' => 'Depends on',
    'predecessors_help' => 'The row number. "12" is finish-to-start; "12FS+2d" adds 2 days of wait; "15SS" starts together with row 15. Separate several with commas.',
    'owner' => 'Owner',
    'start' => 'Start',
    'finish' => 'Finish',
    'float' => 'Float',
    'total_float' => 'Total float',
    'free_float' => 'Free float',
    'critical' => 'Critical',
    'milestone' => 'Milestone',
    'summary' => 'Summary',
    'progress' => 'Progress',
    'cost' => 'Cost',
    'constraint' => 'Constraint',
    'constraint_date' => 'Constraint date',
    'row' => 'Row',

    'created' => 'Task added and plan recalculated.',
    'updated' => 'Task updated and plan recalculated.',
    'deleted' => 'Task deleted and plan recalculated.',
    'moved' => 'Plan reorganized and recalculated.',
    'recalculated' => 'Plan recalculated.',

    'indent' => 'Indent',
    'outdent' => 'Outdent',
    'move_up' => 'Move up',
    'move_down' => 'Move down',

    'cannot_indent' => 'There is no task above it to nest it under. Move it up first, or add a task above.',
    'cannot_outdent' => 'It is already at the top level: there is nowhere to pull it out to.',
    'cannot_up' => 'It is already the first in its group.',
    'cannot_down' => 'It is already the last in its group.',

    'dependency_unreadable' => 'I cannot read ":piece". Try 12, 12FS+2d or 15SS.',
    'dependency_unknown_task' => 'There is no task :reference in this project.',
    'dependency_repeated' => 'Task :reference appears twice. Once is enough.',
    'dependency_on_itself' => 'A task cannot depend on itself.',
    'constraint_needs_date' => 'That constraint needs a date to mean anything.',
    'could_not_calculate' => 'The plan could not be calculated. Check the dependencies.',

    'empty' => 'No tasks yet. Add the first one below — you can type the whole list and organize it afterwards with the arrows.',

    'recalculate' => 'Recalculate',
    'last_run' => 'Last calculation: :when · :count tasks · :ms ms',
    'never_calculated' => 'Not calculated yet.',

    'critical_path' => 'Critical path',
    'critical_explained' => 'These tasks cannot slip by a single day without moving the delivery date.',
    'float_explained' => 'Float is how much a task can slip without moving delivery. At zero, it is critical.',
    'negative_float_explained' => 'Negative float: this task is already late against a committed date.',

    'project_start' => 'Project start',
    'project_finish' => 'Calculated finish',

    'confirm_delete' => 'Delete this task? If it has tasks below it, those go too.',

    'history' => 'History',
    'no_history' => 'No changes recorded yet.',
    'system' => 'System',
    'dates' => 'Calculated dates',
    'successors' => 'These depend on it',
    'calendar_view' => 'Calendar',

];
