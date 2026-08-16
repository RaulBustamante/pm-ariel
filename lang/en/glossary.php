<?php

declare(strict_types=1);

/**
 * Contextual glossary. Each term carries `_label`, a one-line definition and,
 * where it helps, an example from Ariel.
 *
 * The rule when writing here: if the definition uses another glossary term, it
 * is badly written. These sentences are for someone who has never run a formal
 * project, not for someone who already knows.
 */
return [

    'what_is' => 'What is :term?',

    'charter_label' => 'Project charter',
    'charter' => 'The one- or two-page document that authorizes the project: why it exists, what it delivers, who runs it and within what limits.',
    'charter_example' => 'It is what gets signed before the first peso is spent. Without it, everyone remembers a different agreement.',

    'stakeholder_label' => 'Stakeholder',
    'stakeholder' => 'Anyone the project affects or who can affect it, inside or outside the company.',
    'stakeholder_example' => 'The manager who approves the spend, the team that will use the system, and the supplier delivering the equipment.',

    'power_label' => 'Power',
    'power' => 'How much this person can decide, stop or unblock the project.',
    'power_example' => 'Whoever signs the budget has high power even if they never use what gets built.',

    'interest_label' => 'Interest',
    'interest' => 'How much the project outcome changes their daily work.',
    'interest_example' => 'Someone who will use the system every day has high interest even if they decide nothing.',

    'sponsor_label' => 'Sponsor',
    'sponsor' => 'The executive who approves the budget and unblocks what the team cannot resolve on its own.',
    'sponsor_example' => 'When two areas cannot agree, they decide. Without one, the project stops right there.',

    'objective_label' => 'Objective',
    'objective' => 'What you want to achieve, not what you are going to do.',
    'objective_example' => '"Cut the monthly close to one day" is an objective. "Build a system" is an activity.',

    'deliverable_label' => 'Deliverable',
    'deliverable' => 'Something concrete that can be reviewed and approved once it is ready.',
    'deliverable_example' => '"Manual delivered and signed" is a deliverable. "Train the staff" is not: it cannot be reviewed.',

    'success_criteria_label' => 'Success criterion',
    'success_criteria' => 'The concrete test that will say whether the project delivered, agreed before starting.',
    'success_criteria_example' => '"The March close takes one day with zero differences." That can be verified; "make it good" cannot.',

    'assumption_label' => 'Assumption',
    'assumption' => 'Something you are taking as true without having confirmed it.',
    'assumption_example' => '"IT will give us the server in January." If it fails it becomes a problem — which is why you write it down now.',

    'constraint_label' => 'Constraint',
    'constraint' => 'A limit that cannot move: a date, money, people or a rule.',
    'constraint_example' => '"It has to be ready before the May audit" is a constraint, not an objective.',

    'out_of_scope_label' => 'Out of scope',
    'out_of_scope' => 'What the project will NOT do, written down on purpose.',
    'out_of_scope_example' => 'It is the field that prevents the "I thought that was included too" argument three months later.',

    'risk_label' => 'Risk',
    'risk' => 'Something that has not happened yet and that, if it does, changes the project outcome.',
    'risk_example' => 'If it already happened it is not a risk: it is a problem, and it gets handled differently.',

    'probability_label' => 'Probability',
    'probability' => 'How likely that risk is to occur, from 1 to 5.',

    'impact_label' => 'Impact',
    'impact' => 'How much it would hurt if it did occur, from 1 to 5.',

    'risk_owner_label' => 'Risk owner',
    'risk_owner' => 'The person who watches it and raises a hand when it starts getting close.',
    'risk_owner_example' => 'Not necessarily whoever fixes it: whoever notices in time.',

    'risk_response_label' => 'Risk response',
    'risk_response' => 'What will concretely be done about that risk, decided before it happens.',
    'risk_response_example' => 'There are five roads: avoid it, reduce it, hand it to a third party, accept it, or escalate it to whoever can act.',

    'opportunity_label' => 'Opportunity',
    'opportunity' => 'A good risk: something that could happen and would improve the outcome if taken.',
    'opportunity_example' => '"If the supplier delivers early we can launch in high season." Missing it has a cost too.',

    'justification_label' => 'Justification',
    'justification' => 'The reason this project is worth doing rather than another one.',

];
