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
    'predecessors_help' => 'Type the number in the first column of the task that comes first. "1.2" means this one starts when that one finishes; "1.2+2d" leaves 2 days of wait; "1.2SS" makes both start together. Separate several with commas.',
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
    'requested_start' => 'Start date',
    'requested_start_help' => 'The task will not start before this date. If a dependency ends later, the start moves automatically.',
    'deadline' => 'Deadline',
    'deadline_help' => 'Latest committed finish. If the plan can no longer meet it, the task shows negative float.',
    'deadline_before_start' => 'The deadline cannot be before the start date.',
    'row' => 'Row',
    'wbs' => 'No.',
    'reference_of' => 'Number of ":name". This is what you type under "Depends on".',

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

    // Capture inside a package without having to indent afterwards.
    'add_subtask' => 'Subtask',
    'add_subtask_of' => 'Add a subtask inside ":name"',
    'new_subtask_of' => 'New subtask of ":name"',
    'new_subtask_hint' => 'It is born inside the package: no need to indent it afterwards. On save, the form stays here so you can capture the next one.',
    'new_subtask_exit' => 'Create it at the top level instead',

    // Reorganize as a group. One task at a time means one project recalculation
    // and one page reload per arrow click.
    'bulk_title' => 'Move several at once',
    'bulk_hint' => 'Tick the tasks in the first column and pick which package they go into. They all move together, with a single recalculation.',
    'bulk_select' => 'Select ":name" to move it as part of a group',
    'bulk_select_all' => 'Select every visible task',
    'bulk_parent' => 'Put them inside',
    'bulk_top_level' => 'Top level (pull them out of their package)',
    'bulk_apply' => 'Move the selected ones',
    'bulk_selected_count' => ':count selected',
    'bulk_none_selected' => 'You did not tick any task. Tick at least one in the first column.',
    'bulk_moved' => ':count task(s) moved and plan recalculated.',
    'bulk_cycle' => 'A task cannot end up inside one of its own subtasks: ":name" is a descendant of the one you are moving.',
    'bulk_into_itself' => 'A task cannot end up inside itself.',

    'legend_subtask' => 'Creates a subtask already inside this package, with no need to indent it afterwards.',
    'legend_bulk' => 'Tick several tasks to move them into the same package together.',

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
    // --- Waiting ----------------------------------------------------------
    //
    // An axis of its own, not a fourth progress state: a task can be 85 % done
    // and awaiting approval at the same time.
    'waiting' => 'Waiting on',
    'waiting_none' => 'Not waiting on anything',
    'waiting_help' => 'For when the task is not moving because of something outside it: a signature, a user test, an answer. It lives alongside progress rather than replacing it — a task at 85 % can be waiting. It moves no date in the plan.',
    'waiting_approval' => 'Awaiting approval',
    'waiting_uat' => 'In user testing (UAT)',
    'waiting_client' => 'Awaiting client response',
    'waiting_third_party' => 'Awaiting a third party',
    'waiting_blocked' => 'Blocked',
    'waiting_note' => 'Who are you waiting on?',
    'waiting_note_help' => 'Who has to answer, and the ticket number if there is one. For example: "IT, to create the user account" or "Ana Ruiz, UAT sign-off".',
    'waiting_since' => 'Waiting since',
    'waiting_since_date' => 'Waiting since :date (:count day(s))',
    'waiting_days_short' => ':count d',
    'waiting_clock_help' => 'The system sets the date when the wait starts. Changing the kind of wait resets it; fixing the note does not. Reaching 100 % clears the wait on its own.',
    'only_waiting' => 'Only the ones waiting',

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
    'detail_hint_row' => 'Double-click a row — outside the fields — to open the detail of that task. The "..." at the end of the row opens it too.',

    // --- List legend ------------------------------------------------------
    'legend' => 'What does each symbol mean?',
    'legend_summary' => 'Package: groups the tasks below it. Its duration and progress come from them; they are not typed in.',
    'legend_milestone' => 'Milestone: takes no time, marks a date — a delivery, a signature, a kick-off.',
    'legend_critical' => 'If this task slips one day, the whole project delivers one day later.',
    'legend_detail' => 'Opens the task detail: notes, attachments, dependencies and history.',
    'legend_notes' => 'Same as above, but this task already has notes written.',
    'legend_indent' => 'Moves the task into the package above it, or takes it out.',
    'legend_move' => 'Moves the task up or down within its group.',
    'legend_delete' => 'Deletes the task. If it has tasks below it, they go too.',
    'mark_done' => 'Mark as done',

    // --- Depends on, in plain words (stage 9) ------------------------------
    'depends_on' => 'Depends on',
    'depends_on_help' => 'What has to happen before this task can move. Pick the task from the list; no code to learn.',
    'depends_on_none' => 'This task waits for nothing.',
    'add_dependency' => 'Add',
    'which_task' => 'Which task',
    'relationship' => 'When this one can start',
    'lag_days' => 'Days of wait',
    'lag_days_help' => 'How many days to let pass afterwards. Zero if it starts right away; a negative number if it can overlap.',
    'dependency_added' => 'Dependency added and plan recalculated.',
    'dependency_removed' => 'Dependency removed and plan recalculated.',
    'dependency_would_loop' => 'That dependency would make a loop: the task would end up waiting for itself. It was not added, and the plan is unchanged.',
    'blocks' => 'This holds up',
    'blocks_none' => 'No other task waits for this one.',

    // The four relationships, said the way you would say them out loud.
    'rel_FS' => 'After that one finishes',
    'rel_SS' => 'When that one starts',
    'rel_FF' => 'They finish together',
    'rel_SF' => 'Finishes when that one starts',
    'rel_FS_short' => 'after',
    'rel_SS_short' => 'alongside',
    'rel_FF_short' => 'closes with',
    'rel_SF_short' => 'closes when that starts',
    'lag_after' => 'with :days day(s) of wait',
    'lag_before' => 'overlapping by :days day(s)',
    'expression_help' => 'A shortcut for anyone coming from MS Project: "12", "12FS+2d", "15SS". If you would rather not learn it, use "Depends on" in the task detail — it does the same thing from a list.',

    // --- Comments -----------------------------------------------------------
    'comments' => 'What happened here',
    'comments_help' => 'What people said while the work was going on, alongside what the system recorded. The note above says how the task stands today; this says how it got there.',
    'comment_placeholder' => 'Write what happened...',
    'comment_add' => 'Comment',
    'comment_added' => 'Comment saved.',
    'comment_deleted' => 'Comment deleted.',
    'comment_delete_confirm' => 'Delete this comment?',
    'timeline_empty' => 'Nothing here yet. The first comment is what makes the rest useful.',
    'changed' => 'changed',
    'parent' => 'Package',

    'calendar' => 'Working hours for this task',
    'calendar_default' => "The project's",
    'calendar_help' => 'Only if this task runs on different hours: a night shift, a contractor on another schedule.',

];
