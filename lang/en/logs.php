<?php

declare(strict_types=1);

/*
| The text for the fourteen registers that grow during the project.
|
| Eleven status sets cover all fourteen, so "approved" is written once and
| serves both the change requests and the change log.
|
| Each register's help does not describe the screen: it says **what belongs in
| it and what does not**. That is the only thing that keeps the decision log
| from filling up with action items and the issue log with complaints.
*/

return [

    'add' => 'Record',
    'edit' => 'Edit entry',
    'recorded' => 'Recorded as :reference.',
    'amended' => ':reference updated.',
    'deleted' => 'Entry deleted. Its number will not be reused.',
    'empty' => 'This register is still empty.',
    'empty_filtered' => 'No entry matches the filter.',
    'download' => 'Download as PDF',

    'reference' => 'Number',
    'occurred_on' => 'Date',
    'occurred_on_help' => 'When it happened, not when you are typing it.',
    'entry_title' => 'What happened',
    'entry_title_help' => 'One line that still makes sense on its own six months from now.',
    'detail' => 'Detail',
    'owner' => 'Owner',
    'owner_none' => 'No owner',
    'due_on' => 'Due date',
    'priority' => 'Priority',
    'priority_none' => 'No priority',
    'outcome' => 'Outcome',
    'outcome_help' => 'How it ended. Filled in on closing, not on opening.',
    'recorded_by' => 'Recorded by',

    'total' => 'Entries',
    'open' => 'Open',
    'overdue' => 'Overdue',
    'overdue_help' => 'Still open, and the due date has passed.',

    'filter' => 'Filter',
    'filter_clear' => 'Clear filters',
    'filter_search' => 'Search the text',
    'filter_all_statuses' => 'Any status',
    'filter_all_owners' => 'Any owner',
    'showing' => 'Showing :shown of :total.',

    'confirm_delete' => 'Delete this entry? Its number will not be reused.',

    // --- The statuses, by set (config/pmi_logs.php) ----------------------
    'status_open' => 'Open',
    'status_in_progress' => 'In progress',
    'status_resolved' => 'Resolved',
    'status_closed' => 'Closed',

    'status_requested' => 'Requested',
    'status_under_review' => 'Under review',
    'status_approved' => 'Approved',
    'status_rejected' => 'Rejected',
    'status_implemented' => 'Implemented',
    'status_verified' => 'Verified',

    'status_proposed' => 'Proposed',
    'status_decided' => 'Decided',
    'status_superseded' => 'Superseded',

    'status_assumed' => 'Assumed',
    'status_validated' => 'Confirmed',
    'status_invalidated' => 'Proved false',

    'status_captured' => 'Captured',
    'status_applied' => 'Applied',
    'status_shared' => 'Shared',

    'status_draft' => 'Draft',
    'status_issued' => 'Issued',

    'status_drafted' => 'Drafted',
    'status_sent' => 'Sent',
    'status_acknowledged' => 'Acknowledged',

    'status_passed' => 'Passed',
    'status_failed' => 'Failed',
    'status_retest' => 'Retest',

    'status_identified' => 'Identified',
    'status_updated' => 'Updated',

    'status_within_tolerance' => 'Within tolerance',
    'status_out_of_tolerance' => 'Out of tolerance',
    'status_corrected' => 'Corrected',

    // --- The priorities --------------------------------------------------
    'priority_low' => 'Low',
    'priority_medium' => 'Medium',
    'priority_high' => 'High',
    'priority_critical' => 'Critical',

    // --- What belongs in each register, and what does not -----------------
    'help_assumption_log' => 'What is being taken as true without having been checked. An assumption nobody wrote down cannot be confirmed or disproved: it is simply discovered to be false the day it breaks the plan.',
    'help_project_communications' => 'What was communicated, to whom and when. It earns its keep the day someone says they were never told.',
    'help_issue_log' => 'What has already happened and is getting in the way today. If it has not happened yet and might, it is a risk and belongs in the risk register.',
    'help_change_requests' => 'What someone asks to change in scope, schedule or cost. Recorded when it is asked for, not when it is approved: half the value is in the ones that were turned down.',
    'help_decision_log' => 'What was decided, when and why. The why is what matters: without it, three months later the decision looks arbitrary and gets reopened.',
    'help_test_inspection_records' => 'What was tested or inspected and how it came out. It is the evidence that quality was checked, not that checking it was promised.',
    'help_lessons_learned_register' => 'What was learned, while it is being learned. A lesson collected at closing is already a memory; collected the same day it is still useful to the next project.',
    'help_meeting_minutes' => 'What was discussed and what was agreed. Action items coming out of the meeting go to the action item log, with an owner and a date.',
    'help_action_item_log' => 'Open items with an owner and a date. Without both it is not an action item: it is an intention.',
    'help_change_log' => 'Changes already resolved, with their outcome. It is the history of what moved away from the original plan and under whose authority.',
    'help_approved_change_requests' => 'Authorised changes and how their implementation is going. An approved change nobody implements is worse than a rejected one: everyone believes it is already done.',
    'help_risk_updates' => 'How a risk changed: it materialised, went down, went up or stopped applying. It complements the risk register, which says where things stand today but not how they got there.',
    'help_issue_updates' => 'Progress on an issue between the day it was opened and the day it was closed.',
    'help_quality_control_measurements' => 'What was measured and against which tolerance. A measurement without its tolerance is a number, not a control.',

];
