<?php

declare(strict_types=1);

return [

    'title' => 'Alerts',
    'heading' => 'What is worth reviewing',
    'intro' => 'The system checks the plan after every change and flags what threatens delivery. It says what is happening and why; the decision is still yours.',

    'why_no_suggestion' => 'It does not yet propose what to do. Detecting that someone is at 180 % is arithmetic you can verify; telling you whose work to move is a judgement, and without real Ariel projects to learn from, a bad suggestion would cost more than it saves.',

    'none' => 'Nothing to flag. The plan looks healthy.',
    'analyze' => 'Check the plan',
    'analyzed' => 'Plan checked.',
    'last_check' => 'Last check: :when',

    'severity_critical' => 'Threatens delivery',
    'severity_warning' => 'Worth reviewing',
    'severity_info' => 'For your information',

    'workload' => 'Workload',
    'workload_intro' => 'The peak is the highest that person reaches when their tasks overlap. Above 100 % they are promising hours they do not have.',
    'peak' => 'Peak',
    'assigned_tasks' => 'Assigned tasks',
    'capacity' => 'Capacity',
    'no_resources' => 'No resources registered yet.',

    // --- Rules ---

    'overallocated' => ':name reaches :percent % where these overlap: :tasks',
    'overallocated_why' => 'Their capacity is :capacity %. Above that, either the task takes longer than planned or someone works hours that do not exist. The plan says one thing and reality will do another.',

    'duplicated' => ':name appears :count times in the resource list.',
    'duplicated_why' => 'If that is the same person entered twice, their real load is split in half: each record looks calm and the person is not.',

    'duplicated_email' => 'Two resources share the email :email under different names.',

    'critical_without_owner' => ':task is on the critical path and has no owner.',
    'critical_without_owner_why' => 'There is no slack on the critical path: one day late is one day late for delivery. A task with nobody on it is a task nobody is pushing.',

    'negative_float' => ':task is :amount late against its committed date.',
    'negative_float_why' => 'Negative float means the date can no longer be met with the current plan. It does not fix itself: either the date moves, the scope shrinks, or people are added.',

    'overdue' => ':task should have finished on :date and is still at zero per cent.',
    'overdue_why' => 'Either progress is not being captured, or the task has not started. Both matter, and both are cheaper to fix today than in two weeks.',

    'milestone_orphan' => 'Milestone :task depends on nothing.',
    'milestone_orphan_why' => 'A milestone marks that something finished. If nothing feeds it, it stays pinned to the project start and claims delivery happens on day one — which is exactly what nobody notices in a long Gantt.',

];
