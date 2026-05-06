# Sprint plan — Approvio v0.2

Eight sprints. Each ends with green tests, a usable feature, and no regressions
against v0.1. Every sprint assumes the previous one's branch has been merged to
`main` and CI is green before starting.

| Sprint | Goal | Key deliverable |
| --- | --- | --- |
| **1** | Parallel steps | `.parallel()` + quorum rules in `WorkflowBuilder`; engine evaluates quorum on each approval action. |
| **2** | Conditional steps | `.when()` callbacks; skipped steps recorded and bypassed cleanly. |
| **3** | Role resolver | `RoleResolver` via Spatie Permission, opt-in detection, `MissingDependencyException`. |
| **4** | Relationship resolver | `RelationshipResolver` with dot-notation chains; PHPStan ratchet to level 7. |
| **5** | Delegation | Assignee delegates to deputy; `RequestDelegated` event; audit trail. |
| **6** | Escalation + deadlines | `approvio:escalate` command; `StepEscalated` event; expired-request guard. |
| **7** | Resubmit | `resubmit()` on trait + facade; `parent_request_id` migration; `RequestResubmitted` event. |
| **8** | Polish + release | README, PHPStan level 8, MySQL/PostgreSQL CI matrix, `v0.2.0` tag. |

- [`sprint-01-parallel-steps.md`](sprint-01-parallel-steps.md)
- [`sprint-02-conditional-steps.md`](sprint-02-conditional-steps.md)
- [`sprint-03-role-resolver.md`](sprint-03-role-resolver.md)
- [`sprint-04-relationship-resolver.md`](sprint-04-relationship-resolver.md)
- [`sprint-05-delegation.md`](sprint-05-delegation.md)
- [`sprint-06-escalation.md`](sprint-06-escalation.md)
- [`sprint-07-resubmit.md`](sprint-07-resubmit.md)
- [`sprint-08-polish-release.md`](sprint-08-polish-release.md)

## Working rhythm

For each sprint:

1. **Branch** — `git checkout -b sprint/v0.2-XX-short-name`.
2. **TDD** — write failing tests first, then the smallest code that makes them pass.
3. **Verify after each gap** — `vendor/bin/pest && vendor/bin/phpstan analyse`.
4. **Never break v0.1 tests** — if a v0.1 test fails, stop and fix before continuing.
5. **Document** — update `CHANGELOG.md` under `[Unreleased]` as you go.
6. **Merge** — squash to `main` once CI is green.

## What "done" means per sprint

Each sprint file ends with an acceptance checklist. No sprint is done unless every
box is ticked. The v0.1 test suite passing unmodified is an implicit first item on
every checklist.
