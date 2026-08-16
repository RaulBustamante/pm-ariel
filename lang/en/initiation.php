<?php

declare(strict_types=1);

return [

    'title' => 'Project initiation',
    'heading' => 'Start this project well',
    'intro' => 'Four steps. At the end you have the charter, who the stakeholders are, and what could go wrong — which is everything you need to ask for a yes with something behind it.',

    'new_project' => 'New project',
    'start' => 'Start',
    'continue' => 'Pick up where I left off',
    'save_and_continue' => 'Save and continue',
    'save_and_exit' => 'Save and exit',
    'back' => 'Previous',
    'step_of' => 'Step :current of :total',

    'projects' => 'Projects',
    'no_projects' => 'No projects yet. The first one is created through the guided walk-through, which takes about twenty minutes and leaves the documents ready.',

    // --- Steps ---

    'step_justification_title' => 'Why',
    'step_justification_purpose' => 'Why this project exists and what the company gains if it goes well.',

    'step_stakeholders_title' => 'Who cares',
    'step_stakeholders_purpose' => 'Who can help or get in the way, and how to treat each one.',

    'step_charter_title' => 'What it delivers',
    'step_charter_purpose' => 'The objectives, the deliverables, and how you will know it went well.',

    'step_risks_title' => 'What could go wrong',
    'step_risks_purpose' => 'What has not happened yet but would change the outcome, and what will be done about it.',

    // --- Project creation ---

    'project_name' => 'Project name',
    'project_name_help' => 'What someone would call it in a hallway. "Spare parts inventory system", not "Project 2026-04".',
    'project_code' => 'Code',
    'project_code_help' => 'Short and unique. Used to refer to it in emails and reports.',
    'project_description' => 'What it is about, in two lines',
    'project_type' => 'Project type',
    'project_type_help' => 'Preloads the risks and stakeholders that almost always show up in that kind of project. Everything can be deleted and changed.',
    'no_template' => 'Start from scratch, preload nothing',
    'created' => 'Project created. Let us start with the why.',

    // --- Step 1: justification ---

    'field_problem_statement' => 'What is the problem today',
    'help_problem_statement' => 'What hurts right now, without proposing the solution yet. If nothing hurts, maybe the project is not needed.',
    'example_problem_statement' => 'Example: "Every month-end close takes us two days to consolidate five separate spreadsheets, and in three of the last six months there were differences we had to trace by hand."',

    'field_opportunity' => 'What opportunity opens up',
    'help_opportunity' => 'Optional. Sometimes there is no problem, just something to be gained.',

    'field_expected_benefit' => 'What is gained if it goes well',
    'help_expected_benefit' => 'As concrete as possible: hours, money, errors avoided, customers retained. A benefit nobody can notice is a benefit nobody can defend.',
    'example_expected_benefit' => 'Example: "Two working days freed up every month and no differences to trace."',

    'field_alignment' => 'Which company objective it supports',
    'help_alignment' => 'Why this and not something else. It is what leadership will ask.',

    // --- Step 2: stakeholders ---

    'stakeholders_intro' => 'A stakeholder is anyone the project affects or who can affect it — inside or outside. Place them on the matrix and the system proposes how to treat them.',
    'add_stakeholder' => 'Add stakeholder',
    'edit_stakeholder' => 'Edit stakeholder',
    'stakeholder_name' => 'Name or role',
    'stakeholder_name_help' => 'If you do not know the person yet, use the role: "Purchasing Manager".',
    'stakeholder_organization' => 'Organization',
    'stakeholder_role' => 'Role',
    'stakeholder_email' => 'Email',
    'stakeholder_phone' => 'Phone',
    'stakeholder_power' => 'Power',
    'stakeholder_power_help' => 'How much they can decide, stop or unblock this project. 1 is nothing, 5 is that one word from them stops it.',
    'stakeholder_interest' => 'Interest',
    'stakeholder_interest_help' => 'How much the outcome affects them. 1 is they do not care, 5 is their daily work changes.',
    'stakeholder_expectations' => 'What they expect from this project',
    'stakeholder_strategy' => 'How to treat them',
    'stakeholder_strategy_help' => 'The system proposes a strategy based on where they landed on the matrix. You can change it.',
    'stakeholder_created' => 'Stakeholder added.',
    'stakeholder_updated' => 'Stakeholder updated.',
    'stakeholder_deleted' => 'Stakeholder deleted.',
    'no_stakeholders' => 'No stakeholders yet. Start with the obvious one: whoever approves the budget.',

    'matrix_title' => 'Power / interest matrix',
    'matrix_help' => 'Each dot is a stakeholder. The higher and further right, the more attention they need.',
    'matrix_axis_power' => 'Power →',
    'matrix_axis_interest' => 'Interest →',

    'quadrant_manage_closely' => 'Manage closely',
    'quadrant_keep_satisfied' => 'Keep satisfied',
    'quadrant_keep_informed' => 'Keep informed',
    'quadrant_monitor' => 'Monitor',

    'strategy_manage_closely' => 'Involve them in decisions and report directly and often. If they hear about something late, they hear about it angry.',
    'strategy_keep_satisfied' => 'They can stop the project and do not care about detail. Consult them at decision points, without flooding them with progress.',
    'strategy_keep_informed' => 'The outcome affects them but they do not decide. Keep them updated; their support is cheap and worth a lot.',
    'strategy_monitor' => 'They neither decide nor are much affected today. Check now and then in case that changes.',

    // --- Step 3: charter ---

    'charter_intro' => 'This is already mostly assembled from what you captured. Review and adjust it — do not write it from scratch.',
    'field_objectives' => 'Objectives',
    'help_objectives' => 'What you want to achieve. Two or three, not ten. If there are ten, these are several projects.',
    'field_deliverables' => 'Deliverables',
    'help_deliverables' => 'Things that can be reviewed and approved, not activities. "Manual delivered", not "train people".',
    'field_success_criteria' => 'How we will know it went well',
    'help_success_criteria' => 'The concrete test. If it cannot be verified, it is not a criterion: it is a wish.',
    'field_assumptions' => 'Assumptions',
    'help_assumptions' => 'What you are taking for granted. Every assumption that fails becomes a problem, so it is worth writing them down.',
    'field_constraints' => 'Constraints',
    'help_constraints' => 'What cannot move: date, budget, available people, rules.',
    'field_out_of_scope' => 'What it does NOT include',
    'help_out_of_scope' => 'The most useful field in the charter. Whatever is not written here, someone will assume was included.',
    'field_high_level_milestones' => 'Main milestones',
    'help_high_level_milestones' => 'Four or five moments with approximate dates. The detail gets planned later.',
    'field_sponsor' => 'Sponsor',
    'help_sponsor' => 'Who approves the budget and unblocks what the team cannot. Without a sponsor, the project stops at the first obstacle.',

    'suggest' => 'Suggest a draft',
    'suggest_help' => 'Proposes text based on what you already wrote. Always review it: it is a draft, not an answer.',
    'suggestion_ready' => 'Draft proposed below. Review and adjust before saving.',
    'suggestion_empty' => 'There was not enough context to propose anything. Write the problem first and try again.',
    'suggest_risks' => 'Suggest typical risks',
    'suggest_stakeholders' => 'Suggest typical stakeholders',
    'suggestions_added' => ':count suggestions added. Review them: delete what does not apply and adjust the rest.',
    'suggestions_none' => 'There was nothing new to suggest.',

    // --- Step 4: risks ---

    'risks_intro' => 'A risk is something that has not happened yet and that, if it does, changes the outcome. Good ones count too: an opportunity nobody takes is lost all the same.',
    'add_risk' => 'Add risk',
    'edit_risk' => 'Edit risk',
    'risk_code' => 'Code',
    'risk_description' => 'What could happen',
    'risk_description_help' => 'Be concrete. "The supplier delivers late" is useless; say which supplier, of what, and why it could happen.',
    'risk_cause' => 'Why it could happen',
    'risk_effect' => 'What would happen then',
    'risk_category' => 'Category',
    'risk_probability' => 'Probability',
    'risk_probability_help' => '1 is almost impossible, 5 is that it would be odd if it did not happen.',
    'risk_impact' => 'Impact',
    'risk_impact_help' => '1 is barely noticeable, 5 is the project falls over.',
    'risk_kind' => 'Type',
    'risk_kind_threat' => 'Threat',
    'risk_kind_opportunity' => 'Opportunity',
    'risk_status' => 'Status',
    'risk_owner' => 'Owner',
    'risk_owner_help' => 'Who watches this risk. Without a name, nobody watches it.',
    'risk_score' => 'Level',
    'risk_created' => 'Risk added.',
    'risk_updated' => 'Risk updated.',
    'risk_deleted' => 'Risk deleted.',
    'no_risks' => 'No risks recorded yet. A project without risks does not exist; what does not exist is the list.',

    'status_identified' => 'Identified',
    'status_analyzing' => 'Under analysis',
    'status_responding' => 'Response under way',
    'status_closed' => 'Closed',
    'status_materialized' => 'It happened',

    'level_low' => 'Low',
    'level_medium' => 'Medium',
    'level_high' => 'High',
    'level_critical' => 'Critical',

    'matrix_risk_title' => 'Probability and impact matrix',
    'matrix_risk_help' => 'The ones in the top-right corner need a plan, not a note.',

    'add_response' => 'Define what will be done',
    'response_strategy' => 'Strategy',
    'response_description' => 'What exactly will be done',
    'response_owner' => 'Who does it',
    'response_due' => 'By when',
    'response_created' => 'Response recorded.',
    'response_deleted' => 'Response deleted.',
    'no_responses' => 'No response defined',

    'strategy_avoid' => 'Avoid — change the plan so it cannot happen',
    'strategy_mitigate' => 'Mitigate — lower the probability or the impact',
    'strategy_transfer' => 'Transfer — have a third party carry it (insurance, contract)',
    'strategy_accept' => 'Accept — live with it and watch it',
    'strategy_escalate' => 'Escalate — this project is not the one to solve it',

    // --- Completeness ---

    'health' => 'What is missing',
    'health_green' => 'Initiation is complete. You can present it.',
    'health_amber' => 'It can be presented, but there are details worth closing.',
    'health_red' => 'Something the document needs to stand on is still missing.',
    'health_complete_pct' => ':percent % of the walk-through',
    'why' => 'Why it matters',

    'finding_no_problem' => 'The problem is not written down.',
    'finding_no_problem_why' => 'Without a problem there is no project to defend. It is the first thing whoever approves the budget will ask.',
    'finding_no_benefit' => 'What is gained is not written down.',
    'finding_no_benefit_why' => 'A project with no stated benefit cannot be prioritized against another, and at closing nobody will know whether it was worth it.',
    'finding_no_alignment' => 'No company objective was stated.',
    'finding_no_alignment_why' => 'It is what separates a necessary project from one that only matters to a single area.',

    'finding_no_stakeholders' => 'No stakeholders recorded.',
    'finding_no_stakeholders_why' => 'Projects rarely fail for technical reasons. They fail because someone who could stop them found out late.',
    'finding_no_key_stakeholder' => 'Nobody landed in "manage closely".',
    'finding_no_key_stakeholder_why' => 'If truly nobody has high power and high interest, whoever approves may be missing from the list.',
    'finding_no_strategy' => 'No strategy defined for: :names',
    'finding_no_strategy_why' => 'These are the ones who can sink or save the project. How to treat them cannot be left to improvisation.',

    'finding_no_objectives' => 'Objectives are missing.',
    'finding_no_objectives_why' => 'Without objectives the team does not know where to go, and everyone pushes toward their own idea.',
    'finding_no_deliverables' => 'Deliverables are missing.',
    'finding_no_deliverables_why' => 'They are what gets reviewed and approved. Without them there is no way to say anything is finished.',
    'finding_no_success_criteria' => 'How success will be known is missing.',
    'finding_no_success_criteria_why' => 'It is what prevents the argument at closing about whether the project delivered.',
    'finding_no_sponsor' => 'No sponsor designated.',
    'finding_no_sponsor_why' => 'The sponsor unblocks what the team cannot. Without one, the project stops at the first obstacle.',

    'finding_few_risks' => 'Only :count risk(s) recorded; at least :minimum are expected.',
    'finding_few_risks_why' => 'Every project has risks. A short list almost never means there are few, only that nobody looked.',
    'finding_risk_without_response' => 'High risks with no response: :codes',
    'finding_risk_without_response_why' => 'A high risk with no plan is the most common audit finding, and the easiest to avoid.',
    'finding_risk_without_owner' => 'Risks with no owner: :codes',
    'finding_risk_without_owner_why' => 'A risk with no name attached is watched by nobody, even when it is written down.',

    // --- Output ---

    'package' => 'Initiation package',
    'download_package' => 'View the full package',
    'print_hint' => 'Use your browser print dialog to save it as a PDF.',
    'generated_on' => 'Generated on :date',
    'prepared_by' => 'Prepared by',
    'unnamed_stakeholder' => 'Unnamed stakeholder',

    'approve' => 'Approve the charter',
    'approved_on' => 'Approved on :date by :name',
    'not_approved' => 'Not approved',
    'approved' => 'Charter approved.',
    'cannot_approve_incomplete' => 'It cannot be approved while something required is missing. Check the list of pending items.',

];
