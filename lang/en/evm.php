<?php

declare(strict_types=1);

/*
| The earned value report.
|
| Each index carries its short name --the one certified people use-- and one
| line saying **what the number that came out means**, not which formula
| produced it. A board that says "CPI 0.82" and nothing else forces you to ask
| someone, and that someone already knew the answer.
*/

return [

    'title' => 'Earned value',
    'intro' => 'The three figures everything else comes from: how much should be earned by now, how much was actually earned, and how much was actually spent. It is the only way to tell running expensive from running late, which look identical on a progress board.',
    'download' => 'Download as PDF',
    'status_date' => 'Status date',
    'status_date_help' => "Today's indices do not explain a meeting held three weeks ago. Move the date to see what was known then.",
    'recalculate' => 'Recalculate',
    'as_of' => 'As of :date',

    // --- The three figures ------------------------------------------------
    'pv' => 'Planned value',
    'pv_short' => 'PV',
    'pv_help' => 'How much of the budget should be earned at the status date, according to the baseline.',
    'ev' => 'Earned value',
    'ev_short' => 'EV',
    'ev_help' => "How much was actually earned: each task's budget times its captured progress.",
    'ac' => 'Actual cost',
    'ac_short' => 'AC',
    'ac_help' => 'How much was actually spent. Captured task by task; never deduced from progress.',
    'bac' => 'Budget at completion',
    'bac_short' => 'BAC',
    'bac_help' => 'What was committed in the baseline, plus whatever was added to the plan afterwards.',

    // --- The variances ----------------------------------------------------
    'cv' => 'Cost variance',
    'cv_short' => 'CV',
    'cv_help' => 'Earned value minus actual cost. Negative means over budget.',
    'sv' => 'Schedule variance',
    'sv_short' => 'SV',
    'sv_help' => 'Earned value minus planned value, in money. Negative means behind.',

    // --- The indices ------------------------------------------------------
    'cpi' => 'Cost performance index',
    'cpi_short' => 'CPI',
    'cpi_help' => 'How much the work done is worth per unit spent. Below 1.00 is expensive.',
    'spi' => 'Schedule performance index',
    'spi_short' => 'SPI',
    'spi_help' => 'How much progress was made per unit of progress owed. Below 1.00 is late.',

    // --- The forecasts ----------------------------------------------------
    'forecast' => 'Forecast',
    'eac' => 'Estimate at completion',
    'eac_short' => 'EAC',
    'eac_help' => 'What the project ends up costing if what has happened keeps happening.',
    'etc' => 'Estimate to complete',
    'etc_short' => 'ETC',
    'etc_help' => 'What is left to spend between here and the close, per the forecast.',
    'vac' => 'Variance at completion',
    'vac_short' => 'VAC',
    'vac_help' => 'How far past the budget this ends up. Negative means over.',
    'tcpi' => 'To-complete index',
    'tcpi_short' => 'TCPI',
    'tcpi_help' => 'The efficiency the remaining work would have to run at to still fit the budget.',

    // --- The readings in words --------------------------------------------
    'reading' => 'How to read it',
    'cost_ok' => 'Cost is under control: the work done is worth more than it cost.',
    'cost_tight' => 'Cost is running level.',
    'cost_over' => 'More is being spent than the work done is worth.',
    'schedule_ok' => 'Progress is ahead of the plan.',
    'schedule_tight' => 'Progress is level with the plan.',
    'schedule_late' => 'Progress is behind the plan.',
    'forecast_over' => "At today's rate the project ends :amount over budget.",
    'forecast_under' => "At today's rate the project ends :amount under budget.",
    'tcpi_hard' => 'To fit the budget, the remaining work would have to run at :factor efficiency. Past 1.10 that is a renegotiation, not a promise.',

    // --- What is missing before this can be computed -----------------------
    'no_baseline' => "This project has no baseline. Planned value is being computed against today's plan, which adjusts itself: schedule variance will read close to zero almost always. Capture a baseline so the comparison means something.",
    'baseline_used' => 'Measured against the ":name" baseline.',
    'no_actuals_title' => 'Actual cost is missing',
    'no_actuals' => 'Of :started started task(s), :missing have no actual cost captured. The indices that depend on cost — CPI, EAC, ETC and variance at completion — are not computed until they are complete: with half the spend captured they would look spectacular for the simple reason that the other half is missing.',
    'no_actuals_where' => 'It is captured on each task, next to progress.',
    'nothing_started' => 'No task has started yet, so there is nothing to measure.',
    'not_available' => 'Cannot be computed',

    // --- The per-task table -----------------------------------------------
    'by_task' => 'Line by line',
    'task' => 'Task',
    'budget' => 'Budget',
    'progress' => 'Progress',
    'actual_cost' => 'Actual cost',
    'actual_cost_help' => 'What it actually cost. Leave it empty until you know: a zero reads as free.',

];
