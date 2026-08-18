<?php

declare(strict_types=1);

/*
| Acceptance records: the fourth and last species in the catalogue.
|
| The hardest-working text here is the one explaining **what the signature is
| and is not**. The system has no electronic signature and does not pretend to:
| what gets recorded is that a named person with a role accepted on a date, and
| who entered it. A seal that promises more than it is worth is worse than no
| seal, and that sentence belongs on the screen, not only in the code.
*/

return [

    'open' => 'Open a record',
    'edit' => 'Correct the draft',
    'opened' => 'Record :reference opened as a draft.',
    'amended' => ':reference corrected.',
    'deleted' => 'Draft deleted. Its number will not be reused.',
    'empty' => 'No records of this kind yet.',
    'download' => 'Download as PDF',

    'reference' => 'Number',
    'subject' => 'What is being accepted',
    'subject_help' => 'What is being received, said the way the person receiving it would say it.',
    'detail' => 'What it includes',
    'detail_help' => 'The scope of what was delivered. Whatever is left out here is what gets argued about later.',
    'deliverable' => 'Deliverable in the plan',
    'deliverable_none' => 'Not linked to a task',
    'deliverable_help' => 'Linking it to the plan is what lets you trace "the module was accepted" back to the task that produced it.',

    'decision' => 'Answer',
    'decision_accepted' => 'Accepted',
    'decision_accepted_with_reservations' => 'Accepted with reservations',
    'decision_rejected' => 'Rejected',
    'decision_help' => '"With reservations" exists on purpose: without it, someone receiving something almost good has to accept all of it or reject all of it, and neither is what happened in the meeting.',
    'reservations' => 'Reservations and conditions',
    'reservations_help' => 'What is missing or being corrected, and by when. This is the part of the record people read three months later.',
    'reservations_required' => 'If it is accepted with reservations or rejected, say which. A record claiming there are conditions without naming any gets argued about exactly as if it did not exist.',

    'accepted_by' => 'Who accepts',
    'accepted_by_name' => 'Name',
    'accepted_by_name_help' => 'Free text on purpose: whoever receives is almost always outside the project team and has no account in the system.',
    'accepted_by_role' => 'Role',
    'accepted_by_org' => 'Area or company',
    'accepted_on' => 'Acceptance date',

    // --- The signature ----------------------------------------------------
    'sign' => 'Sign and archive',
    'signed' => 'Record :reference signed and archived.',
    'sign_help' => 'On signing, the record becomes **immutable** and its PDF is archived with a version number, a date and a fingerprint. If what was accepted later changes, you open another record and the change leaves a trail.',
    'sign_confirm' => 'Sign this record? It cannot be edited or deleted afterwards.',
    'sign_disclaimer' => 'This is not an electronic signature: the system has none and does not pretend to. What is recorded is that the named person accepted on the stated date, and who entered it into the system.',
    'already_signed' => 'This record is already signed.',
    'signed_cannot_be_deleted' => 'A signed record is not deleted: it is the record of what was accepted.',
    'signed_on' => 'Signed on :date',
    'recorded_by' => 'Entered by :who',
    'draft' => 'Draft',
    'draft_warning' => 'An unsigned record is worth nothing: it can still be edited and it is not archived.',
    'checksum' => 'Fingerprint',
    'not_signed_yet' => 'Unsigned',

    // --- The figures on top ------------------------------------------------
    'total' => 'Records',
    'signed_count' => 'Signed',
    'draft_count' => 'Drafts',
    'rejected_count' => 'Rejected',

    // --- What gets opened in each kind -------------------------------------
    'help_deliverable_acceptance_records' => 'One per deliverable received. It is the proof that someone outside the team said yes, they were taking it — and with what reservations. Without it, "it was delivered" is the word of whoever delivered against the word of whoever received.',
    'help_acceptance_signoff' => 'The record for the whole project: what was promised was delivered and the sponsor takes it. It is what formally closes the project; without it a project does not end, it just stops having activity.',

];
