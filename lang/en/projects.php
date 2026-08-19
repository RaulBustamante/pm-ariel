<?php

declare(strict_types=1);

return [

    'settings' => 'Settings',
    'details' => 'Project details',

    'currency' => 'Currency',
    'start_help' => 'Changing this date recalculates the whole plan: every task moves with it.',

    'status_draft' => 'Draft',
    'status_active' => 'Active',
    'status_on_hold' => 'On hold',
    'status_closed' => 'Closed',
    'status_cancelled' => 'Cancelled',

    'updated' => 'Project updated.',
    'updated_and_rescheduled' => 'Project updated and plan recalculated with the new date.',

    'members' => 'Members',
    'members_help' => 'Membership is what grants edit rights. Managing someone on the project grants read access, never write.',
    'add_member' => 'Add member',
    'project_role' => 'Project role',
    'owner' => 'owner',
    'role_manager' => 'Project manager',
    'role_member' => 'Member',
    'role_viewer' => 'Read only',
    'member_added' => 'Member added.',
    'member_removed' => 'Member removed.',
    'cannot_remove_owner' => 'The project owner cannot be removed: they would lose the ability to edit their own project and an administrator would be needed to restore it.',

    'baselines' => 'Baselines',
    'baselines_help' => 'A baseline freezes the plan as it was committed. It cannot be edited or deleted: capture another one instead.',
    'baseline_name' => 'Name',
    'baseline_default_name' => 'Baseline of :date',
    'capture_baseline' => 'Capture baseline',
    'baseline_captured' => 'Baseline captured.',
    'baseline_active' => 'Current',
    'no_baselines' => 'None yet. Capture one once the plan is agreed.',
    'baseline_needs_tasks' => 'There are no tasks to freeze. Capture the plan first.',

    'baseline_comparison' => 'Baseline comparison',
    'start_variance' => 'Start variance',
    'finish_variance' => 'Finish variance',
    'cost_variance' => 'Cost variance',
    'variance_help' => 'In working time, not calendar days: a weekend is not a delay.',
    'on_time' => 'On time',
    'task_is_new' => 'new',
    'removed_tasks' => 'Removed tasks',
    'removed_help' => 'They were committed and are no longer in the plan.',
    'removed_warning' => 'These tasks were committed in the baseline and are no longer in the plan:',

    'danger_zone' => 'Delete this project',
    'delete' => 'Delete project',
    'delete_help' => 'This deletes :code along with its tasks, risks, stakeholders and baselines. It disappears from every screen. The audit log is kept, so who deleted it and when stays on record.',
    'delete_type_code' => 'Type :code to confirm',
    'delete_confirmation_failed' => 'Nothing was deleted: what you typed does not match :code.',
    'deleted' => 'Project :name was deleted.',
    'planned_finish' => 'Committed date',
    'planned_finish_help' => 'When it was promised to finish. That is a different thing from the date the plan calculates, and the gap between the two is the conversation worth having early.',
    'committed_vs_calculated' => 'Committed :committed · the plan calculates :calculated',
    'over_committed' => 'The plan finishes :days day(s) after what was committed.',

];
