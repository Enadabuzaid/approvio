<?php

declare(strict_types=1);

use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
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
