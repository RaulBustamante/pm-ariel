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

    'showing_capped' => 'Showing :shown of :total tasks. Use the filter to narrow the list.',

    // --- Work tracking (stage 8) -----------------------------------------
    'state' => 'State',
    'state_todo' => 'Not started',
    'state_doing' => 'In progress',
    'state_done' => 'Done',
    'state_help' => 'Derived from captured progress: 0 % not started, 100 % done. There is no separate state to keep in sync.',
    'notes' => 'Notes',
    'notes_help' => 'What someone needs to know to work this task, and what was agreed while working it. Shown on the detail and flagged on the list and the board.',
    'has_notes' => 'Has notes',
    'real_dates' => 'Actual dates',
    'actual_start' => 'Started',
    'actual_finish' => 'Finished',
    'not_started_yet' => 'Not started yet.',
    'in_progress_since' => 'In progress since :date.',
    'finished_on' => 'Finished on :date.',
    'finished_late' => ':days day(s) later than planned.',
    'finished_early' => ':days day(s) earlier than planned.',
    'finished_on_time' => 'On the planned date.',
    'real_dates_help' => 'Written automatically when progress is captured: the start as soon as it passes zero, the finish on reaching 100 %. Nobody types them.',
    'open_detail' => 'Open the detail',
    'detail_hint' => 'Double-click a card to open its detail.',
    'mark_done' => 'Mark as done',

];
