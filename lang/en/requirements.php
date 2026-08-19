<?php

declare(strict_types=1);

/*
| Requirements and their traceability matrix.
|
| The matrix does not exist to have the table: it exists to produce **two
| numbers** --what was asked for and nobody is building, and what is being built
| that nobody asked for--. The second is almost never looked for, and is usually
| the more expensive one.
*/

return [

    'title' => 'Requirements and traceability',
    'intro' => 'The requirements documentation explains the scope to a person. This answers a different question: which task delivers each requirement, and where each piece of work came from. That needs requirements with their own code, not a paragraph.',
    'matrix' => 'Traceability matrix',
    'add' => 'Add a requirement',
    'empty' => 'No requirements captured yet.',
    'saved' => 'Requirement saved.',
    'deleted' => 'Requirement deleted.',

    'reference' => 'Code',
    'description' => 'What was asked for',
    'description_help' => 'One line you can check. "Make it fast" is not a requirement; "the monthly close takes one day" is.',
    'origin' => 'Who asked for it',
    'origin_help' => 'A stakeholder, a standard, the charter. A requirement with no origin cannot be negotiated when scope has to be cut, because nobody knows who would have to be convinced.',
    'priority' => 'Priority',
    'priority_must' => 'Must have',
    'priority_should' => 'Should have',
    'priority_could' => 'Could have',
    'delivered_by' => 'Delivered by',
    'delivered_by_help' => 'The task in the plan that fulfils it. Leave it empty if there is none yet: that gap is exactly what this screen exists to find.',
    'nobody' => 'Nobody delivers it',
    'nobody_yet' => 'Nobody yet',
    'acceptance' => 'How it gets checked',
    'acceptance_help' => 'Without this, "delivered" is the opinion of whoever delivered.',

    'status_proposed' => 'Proposed',
    'status_approved' => 'Approved',
    'status_delivered' => 'Delivered',
    'status_verified' => 'Verified',
    'status_dropped' => 'Dropped',

    'orphans' => 'Asked for, nobody building it',
    'orphans_help' => 'Requirements with no task delivering them. It is the most expensive thing to find late: something gets agreed, nobody puts it in the plan, and it surfaces on delivery day.',
    'unrequested' => 'Being built, nobody asked',
    'unrequested_help' => 'Tasks that fulfil no captured requirement. Almost never looked for, and usually costlier than the above: it is work being paid for that nobody asked for.',

];
