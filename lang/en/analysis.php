<?php

declare(strict_types=1);

return [

    'title' => 'Analysis',
    'workload' => 'Resource workload',
    'workload_help' => 'Each bar is a week. The dashed line is capacity: anything above it is work that does not fit the working day, and there either the task takes longer than planned or somebody works hours that do not exist.',
    'workload_reader' => ':name peaks at :peak hours in a week, against a capacity of :capacity hours.',
    'weeks' => 'weeks',
    'peak' => 'Peak',
    'capacity' => 'capacity',
    'over' => 'over',
    'no_workload' => 'No work assigned to any resource yet.',
    'hours' => 'Hours distribution',
    'by_phase' => 'Cost by phase',
    'phase' => 'Phase',
    'no_costs' => 'No cost captured yet.',
    'vs_baseline' => 'Against the baseline',
    'baseline_cost' => 'Committed',
    'current_cost' => 'Today',
    'variance' => 'Variance',
    'no_baseline' => 'This project has no baseline captured, so there is nothing to compare the cost against. Capture one from the project settings.',
    'baseline_before_costs' => 'The variance is very large. If this baseline was captured before resource costs were loaded it only froze the fixed cost: the difference would be a change of method rather than an overrun. Worth capturing a fresh baseline.',
];
