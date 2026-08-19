<?php

declare(strict_types=1);

/*
| The documents that generate themselves.
|
| Two texts per document, neither decorative:
|
|   `help_*`   where it comes from. A report that does not say what data it was
|              built from forces you to either trust it or not use it, and the
|              second usually wins.
|
|   `empty_*`  **what to capture so it stops being empty**, and where. An empty
|              derived document is not fixed by typing into its own screen: it
|              is fixed on the screen where the data lives. Saying which is the
|              difference between a useless document and an instruction.
*/

return [

    'download' => 'Download as PDF',
    'empty' => 'This document still comes out empty.',
    'no_spi' => 'Missing a baseline or captured progress.',
    'no_actuals' => 'Actual cost has not been captured on the started tasks.',

    // --- Column headings ---------------------------------------------------
    'col_wbs' => 'WBS',
    'col_name' => 'Name',
    'col_detail' => 'What it involves',
    'col_owner' => 'Owner',
    'col_duration' => 'Duration',
    'col_start' => 'Start',
    'col_finish' => 'Finish',
    'col_cost' => 'Cost',
    'col_predecessors' => 'Depends on',
    'col_constraint' => 'Constraint',
    'col_float' => 'Float',
    'col_critical' => 'Critical path',
    'col_kind' => 'Type',
    'col_role' => 'Role',
    'col_rate' => 'Rate',
    'col_capacity' => 'Capacity',
    'col_supplier' => 'Supplier',
    'col_origin' => 'Origin',
    'col_baseline_cost' => 'Committed',
    'col_current_cost' => 'Today',
    'col_variance' => 'Difference',
    'col_code' => 'Code',
    'col_description' => 'Description',
    'col_category' => 'Category',
    'col_probability' => 'Prob.',
    'col_impact' => 'Impact',
    'col_level' => 'Level',
    'col_status' => 'Status',
    'col_organization' => 'Organisation',
    'col_power' => 'Power',
    'col_interest' => 'Interest',
    'col_quadrant' => 'What to do',
    'col_strategy' => 'Strategy',
    'col_expectations' => 'What they expect',
    'col_measure' => 'Indicator',
    'col_value' => 'Value',
    'col_reading' => 'How to read it',
    'col_reference' => 'Number',
    'col_occurred_on' => 'Date',
    'col_title' => 'What happened',
    'col_outcome' => 'Recommendation',

    // --- Stakeholder quadrants ---------------------------------------------
    'quadrant_manage' => 'Manage closely',
    'quadrant_satisfy' => 'Keep satisfied',
    'quadrant_inform' => 'Keep informed',
    'quadrant_monitor' => 'Monitor with little effort',

    // --- Indicators ---------------------------------------------------------
    'measure_planned_finish' => 'Finish per the plan',
    'measure_spi' => 'Schedule performance index (SPI)',
    'measure_forecast_finish' => "Finish at today's rate",
    'measure_tasks_done' => 'Tasks closed',
    'measure_progress' => 'Earned progress',
    'measure_budget' => 'Budget',
    'measure_actual_cost' => 'Actual cost',
    'measure_cost_index' => 'Cost performance index (CPI)',
    'measure_schedule_index' => 'Schedule performance index (SPI)',
    'forecast_late' => "At today's rate this delivers :days day(s) later than planned.",
    'forecast_early' => "At today's rate this delivers :days day(s) earlier than planned.",
    'forecast_blocked' => 'No forecast without a baseline and captured progress. An invented forecast gets believed.',

    // --- Where each one comes from -------------------------------------------
    'help_wbs_dictionary' => 'Every package and every task in the plan, with what it involves, who owns it, how long it takes and what it costs. It comes from the work plan; nothing is captured here.',
    'help_activity_attributes' => 'What the standard asks you to document about each activity beyond its name: what it depends on, what constraint it carries, how much float is left and whether it sits on the critical path.',
    'help_resource_breakdown_structure' => "The RBS: the project's resources grouped by kind, with their rate, their capacity and whether they are in-house or external.",
    'help_cost_baseline' => 'What was committed in the baseline against what it costs today, line by line. With no baseline there is nothing to compare against and the document comes out empty on purpose.',
    'help_risk_report' => 'The formal report on the risk register, ordered from highest to lowest exposure. A report ordered by code forces you to read all of it to find the one that matters.',
    'help_stakeholder_engagement_plan' => 'What to do with each stakeholder based on where they land on the power-interest grid. The quadrant is derived by the system; the strategy and expectations are captured under Stakeholders.',
    'help_schedule_forecasts' => 'The progress index taken to the calendar. SPI says "you are at 68 % of the rate owed" and nobody knows what to do with that; in dates it says when this finishes if nothing changes.',
    'help_lessons_learned_report' => 'The report on the lessons register that already grows during the project. Nothing is captured twice: it is ordered and printed.',
    'help_final_project_report' => 'The figures the project closes with: how much got done, what it cost, and how that compares with what was committed.',

    // --- What is missing when it comes out empty -------------------------------
    'empty_wbs_dictionary' => 'Capture tasks in the work plan.',
    'empty_activity_attributes' => 'Capture tasks in the work plan.',
    'empty_resource_breakdown_structure' => 'Add resources under the Resources tab: who works and what gets consumed.',
    'empty_cost_baseline' => 'Capture a baseline from the project settings. Without one there is no commitment to compare against.',
    'empty_risk_report' => 'Register risks in the initiation walkthrough.',
    'empty_stakeholder_engagement_plan' => 'Register stakeholders in the initiation walkthrough.',
    'empty_schedule_forecasts' => 'A baseline and captured progress are needed.',
    'empty_lessons_learned_report' => 'Record lessons in their register, in the document centre. A lesson collected at closing is already a memory.',
    'empty_final_project_report' => 'Capture tasks and progress in the work plan.',

];
