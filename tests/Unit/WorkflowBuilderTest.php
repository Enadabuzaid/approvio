<?php

declare(strict_types=1);

use Enadstack\Approvio\Enums\QuorumRule;
use Enadstack\Approvio\Enums\StepType;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseParallelNofMWorkflow;
use Enadstack\Approvio\Tests\Fixtures\Workflows\ExpenseTwoStepWorkflow;
use Enadstack\Approvio\Workflow\WorkflowBuilder;
use Enadstack\Approvio\Workflow\WorkflowDefinition;

it('builds a definition from a Workflow class', function () {
    $workflow = new ExpenseTwoStepWorkflow();
    $definition = $workflow->toDefinition();

    expect($definition)->toBeInstanceOf(WorkflowDefinition::class)
        ->and($definition->slug)->toBe('two-step')
        ->and($definition->version)->toBe(1)
        ->and($definition->approvableType)->toBe(TestExpense::class)
        ->and($definition->stepCount())->toBe(2);
});

it('preserves step ordering', function () {
    $workflow = new ExpenseTwoStepWorkflow();
    $definition = $workflow->toDefinition();

    expect($definition->stepAt(0)->name)->toBe('manager-review')
        ->and($definition->stepAt(1)->name)->toBe('finance-review');
});

it('throws when a step is missing approvers', function () {
    $builder = new WorkflowBuilder('test', 1, 'App\\TestModel');
    $builder->step('lonely-step'); // no approvers() call

    $builder->build();
})->throws(LogicException::class, 'missing approvers');

it('parallel() sets step type to Parallel', function () {
    $builder = new WorkflowBuilder('test', 1, 'App\\TestModel');
    $builder->step('committee')
        ->approvers(fn () => TestUser::all())
        ->parallel();

    $definition = $builder->build();

    expect($definition->stepAt(0)->type)->toBe(StepType::Parallel);
});

it('quorum() sets rule and count on the step', function () {
    $workflow = new ExpenseParallelNofMWorkflow();
    $definition = $workflow->toDefinition();
    $step = $definition->stepAt(0);

    expect($step->type)->toBe(StepType::Parallel)
        ->and($step->quorumRule)->toBe(QuorumRule::NofM)
        ->and($step->quorumCount)->toBe(2);
});

it('throws when n_of_m quorum is declared without a count', function () {
    $builder = new WorkflowBuilder('test', 1, 'App\\TestModel');
    $builder->step('committee')
        ->approvers(fn () => TestUser::all())
        ->parallel()
        ->quorum('n_of_m');

    $builder->build();
})->throws(\InvalidArgumentException::class, 'n_of_m');

it('throws when n_of_m quorum count is less than 1', function () {
    $builder = new WorkflowBuilder('test', 1, 'App\\TestModel');
    $builder->step('committee')
        ->approvers(fn () => TestUser::all())
        ->parallel()
        ->quorum('n_of_m', 0);

    $builder->build();
})->throws(\InvalidArgumentException::class, 'n_of_m');

it('sequential steps default to Any quorum and Sequential type', function () {
    $workflow = new ExpenseTwoStepWorkflow();
    $definition = $workflow->toDefinition();
    $step = $definition->stepAt(0);

    expect($step->type)->toBe(StepType::Sequential)
        ->and($step->quorumRule)->toBe(QuorumRule::Any)
        ->and($step->quorumCount)->toBeNull();
});
