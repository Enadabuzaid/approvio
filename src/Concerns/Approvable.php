<?php

declare(strict_types=1);

namespace Enadstack\Approvio\Concerns;

use Enadstack\Approvio\Contracts\ApprovalStrategy;
use Enadstack\Approvio\Engine\ApprovalEngine;
use Enadstack\Approvio\Models\ApprovalRequest;
use Enadstack\Approvio\Strategies\SoftApproval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Add to any Eloquent model to enable approval workflows.
 *
 * Required hooks on the model:
 *
 *   protected array $approvalWorkflows = [
 *       'submission' => \App\Approvals\ExpenseSubmissionWorkflow::class,
 *   ];
 *
 * Optional:
 *
 *   protected string $approvalStrategy = \Enadstack\Approvio\Strategies\DraftApproval::class;
 *
 * @mixin Model
 */
trait Approvable
{
    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable');
    }

    /**
     * Submit this model for approval against the given workflow slug.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $changes  For draft strategy, the proposed changes.
     */
    public function requestApproval(
        string $workflow = 'default',
        ?Model $requester = null,
        array $context = [],
        array $changes = [],
    ): ApprovalRequest {
        $engine = app(ApprovalEngine::class);
        $strategy = $this->resolveApprovalStrategy();

        $requester ??= auth()->user();

        return $engine->submit(
            approvable: $this,
            workflowSlug: $workflow,
            strategy: $strategy,
            requester: $requester instanceof Model ? $requester : null,
            context: $context,
            changes: $changes,
        );
    }

    /**
     * Convenience: submit with a draft change set, regardless of strategy.
     * If the model uses SoftApproval, the changes are stored in context only.
     *
     * @param  array<string, mixed>  $changes
     */
    public function requestApprovalFor(
        array $changes,
        string $workflow = 'default',
        array $context = [],
    ): ApprovalRequest {
        return $this->requestApproval(
            workflow: $workflow,
            context: $context,
            changes: $changes,
        );
    }

    public function pendingApprovalRequest(): ?ApprovalRequest
    {
        return $this->approvalRequests()
            ->whereIn('status', ['pending', 'in_review'])
            ->latest()
            ->first();
    }

    public function hasPendingApproval(): bool
    {
        return $this->pendingApprovalRequest() !== null;
    }

    public function latestApprovalRequest(): ?ApprovalRequest
    {
        return $this->approvalRequests()->latest()->first();
    }

    /**
     * Expose the protected $approvalWorkflows map so WorkflowSource implementations
     * can read it without going through Eloquent's __get(), which cannot access
     * protected properties and returns null instead.
     *
     * @return array<string, class-string>
     */
    public function getApprovalWorkflows(): array
    {
        return $this->approvalWorkflows ?? [];
    }

    public function resolveApprovalStrategy(): ApprovalStrategy
    {
        $class = property_exists($this, 'approvalStrategy') && ! empty($this->approvalStrategy)
            ? $this->approvalStrategy
            : config('approvio.default_strategy', SoftApproval::class);

        return app($class);
    }
}
