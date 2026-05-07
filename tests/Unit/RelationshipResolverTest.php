<?php

declare(strict_types=1);

use Enadstack\Approvio\Resolvers\Approvers\RelationshipResolver;
use Enadstack\Approvio\Tests\Fixtures\Models\TestExpense;
use Enadstack\Approvio\Tests\Fixtures\Models\TestUser;
use Illuminate\Support\Collection;

it('resolves a single-hop relation that returns a Model', function () {
    $user = new TestUser(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']);
    $expense = new TestExpense(['user_id' => 1, 'title' => 'T', 'amount' => 10]);
    $expense->setRelation('user', $user);

    $resolver = new RelationshipResolver('user');
    $result = $resolver->resolve($expense);

    expect($result)->toBeInstanceOf(Collection::class)
        ->and($result)->toHaveCount(1)
        ->and($result->first())->toBe($user);
});

it('resolves a multi-hop chain', function () {
    $manager = new TestUser(['id' => 2, 'name' => 'Manager', 'email' => 'mgr@example.com']);
    $user = new TestUser(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']);
    $user->setRelation('manager', $manager);

    $expense = new TestExpense(['user_id' => 1, 'title' => 'T', 'amount' => 10]);
    $expense->setRelation('user', $user);

    $resolver = new RelationshipResolver('user.manager');
    $result = $resolver->resolve($expense);

    expect($result)->toHaveCount(1)
        ->and($result->first())->toBe($manager);
});

it('resolves a chain ending in a Collection', function () {
    $members = collect([
        new TestUser(['id' => 1, 'name' => 'Alice', 'email' => 'a@example.com']),
        new TestUser(['id' => 2, 'name' => 'Bob', 'email' => 'b@example.com']),
    ]);

    $expense = new TestExpense(['title' => 'T', 'amount' => 10]);
    $expense->setRelation('committee', $members);

    $resolver = new RelationshipResolver('committee');
    $result = $resolver->resolve($expense);

    expect($result)->toHaveCount(2);
});

it('returns an empty collection when a hop returns null', function () {
    $expense = new TestExpense(['user_id' => null, 'title' => 'T', 'amount' => 10]);
    $expense->setRelation('user', null);

    $resolver = new RelationshipResolver('user');
    $result = $resolver->resolve($expense);

    expect($result)->toBeEmpty();
});

it('returns an empty collection for an empty chain string', function () {
    $expense = new TestExpense(['title' => 'T', 'amount' => 10]);

    $resolver = new RelationshipResolver('');
    $result = $resolver->resolve($expense);

    expect($result)->toBeEmpty();
});
