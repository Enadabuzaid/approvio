# Sprint plan — Approvio v0.1

Five sprints, each shippable in roughly a focused day or two of solo work.
Sprints are sized so each one ends with **green tests + something usable**.
You can stop after any sprint and still have something coherent.

| Sprint | Goal | Outcome |
| --- | --- | --- |
| **1** | Foundation | Composer-installable package shell, migrations run, service provider boots. |
| **2** | Engine core | Submit + approve flow works end-to-end with a single-step workflow. |
| **3** | Multi-step + reject + cancel | Full lifecycle. State machine enforced. |
| **4** | Strategies + tenancy | Both `SoftApproval` and `DraftApproval` work. Column-based tenancy works. |
| **5** | Polish + release | Docs, CI green, PHPStan green, tag `v0.1.0`. |

Each sprint has its own file with concrete tasks, acceptance criteria, and
the test cases that prove it's done.

- [`sprint-01-foundation.md`](sprint-01-foundation.md)
- [`sprint-02-engine-core.md`](sprint-02-engine-core.md)
- [`sprint-03-multi-step-reject-cancel.md`](sprint-03-multi-step-reject-cancel.md)
- [`sprint-04-strategies-tenancy.md`](sprint-04-strategies-tenancy.md)
- [`sprint-05-polish-release.md`](sprint-05-polish-release.md)

## Working rhythm

For each sprint:

1. **Plan** — read the sprint file, list the files you'll touch.
2. **Branch** — `git checkout -b sprint/XX-short-name`.
3. **TDD** — write failing tests, then code.
4. **Verify** — `vendor/bin/pest && vendor/bin/phpstan analyse`.
5. **Document** — update `CHANGELOG.md` under `[Unreleased]`.
6. **Merge** — squash to `main` once CI is green.

## What "done" means per sprint

Each sprint file ends with an acceptance checklist. **No sprint is done unless
every box is ticked.** This is how the package stays honest as it grows.
