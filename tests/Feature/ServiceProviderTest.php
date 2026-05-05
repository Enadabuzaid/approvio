<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('boots the service provider', function () {
    expect(app(\Enadstack\Approvio\Approvio::class))
        ->toBeInstanceOf(\Enadstack\Approvio\Approvio::class);
});

it('runs all migrations cleanly', function () {
    $tables = [
        'approval_workflows',
        'approval_requests',
        'approval_request_steps',
        'approval_step_assignees',
        'approval_actions',
    ];

    foreach ($tables as $t) {
        expect(Schema::hasTable($t))->toBeTrue("Table [{$t}] should exist after migrations run.");
    }
});
