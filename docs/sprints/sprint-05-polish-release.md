# Sprint 5 — Polish + release

> **Goal:** v0.1.0 tagged on Packagist. Documentation that lets a stranger
> install and use the package in 10 minutes. CI green across the matrix.
>
> **Why it matters:** OSS lives or dies on the first impression. A working
> package with a poor README gets ignored.

## Outcomes

When this sprint is done:

- Anyone can `composer require enadstack/approvio`, follow the README,
  and have a working approval flow in their own app within 10 minutes.
- The CI matrix is green across PHP 8.2/8.3/8.4 × Laravel 11/12.
- The package is published on Packagist and tagged `v0.1.0`.
- The repo has the badges, license, and basic SEO that make it
  discoverable.

## Tasks

### 1. README polish

- [ ] Hero section: one-line description + 3-bullet "why use this".
- [ ] Badges: tests CI, static analysis CI, license, latest version,
      downloads (Packagist).
- [ ] Installation block (composer + publish + migrate).
- [ ] Quickstart that walks through:
  - [ ] Adding the trait to a model (with an `approval_status` column note).
  - [ ] Adding `HasApprovalActions` to the User.
  - [ ] Defining a workflow class.
  - [ ] Submitting + approving + rejecting + querying.
- [ ] Strategies section explaining `SoftApproval` vs `DraftApproval`.
- [ ] Multi-tenancy section.
- [ ] Audit log section.
- [ ] Events table.
- [ ] Configuration reference summary.
- [ ] Roadmap section pointing to v0.2/0.3/0.4.
- [ ] Contributing + License pointers.

### 2. Inline docblocks

- [ ] Every public class has a class-level docblock describing its purpose.
- [ ] Every public method has a docblock with `@param` and `@return` where
      types alone don't tell the full story.
- [ ] Every contract has a docblock listing known implementations.

### 3. Examples folder (optional but recommended)

- [ ] `examples/expense-approval/` — minimal Laravel sample showing the
      Expense model, a workflow class, and a controller. Or:
- [ ] Put the sample directly in the README's quickstart.

### 4. CI sweep

- [ ] CI passes on the full matrix (PHP 8.2/8.3/8.4 × Laravel 11/12).
- [ ] PHPStan passes at level 6.
- [ ] Test coverage report shows ≥ 80% on the engine, strategies,
      state machine.

### 5. Release prep

- [ ] `CHANGELOG.md` — move `[Unreleased]` content to a new `[0.1.0]`
      section with the release date.
- [ ] Verify `composer.json` `name`, `description`, `keywords`,
      `homepage`, `license`, `authors` are all correct.
- [ ] Verify `extra.laravel.providers` is listed for auto-discovery.
- [ ] Tag `v0.1.0` and push tags.

### 6. Packagist

- [ ] Submit the GitHub repo URL to Packagist.
- [ ] Set up the GitHub webhook so future tags auto-update Packagist.
- [ ] Verify the package page shows install instructions and the README.

### 7. Sandbox dry run

- [ ] Create a fresh Laravel 11 app.
- [ ] `composer require enadstack/approvio:^0.1`.
- [ ] Follow the README quickstart verbatim.
- [ ] Confirm the full submit → approve → query cycle works.
- [ ] If it doesn't, fix the README or the package — whichever is wrong.

### 8. Announcement (optional)

- [ ] LinkedIn post / Twitter thread / dev.to article.
- [ ] Pin the repo on your GitHub profile.
- [ ] Add to your portfolio site.

## Acceptance checklist

- [ ] CI matrix green (all 6 cells).
- [ ] PHPStan green at the configured level.
- [ ] Test coverage ≥ 80% on core code.
- [ ] README quickstart works in a fresh Laravel app.
- [ ] `v0.1.0` tag pushed.
- [ ] Visible on Packagist with correct metadata.
- [ ] At least one external user (could be you on a clean machine) has run
      the quickstart and confirmed it works end-to-end.

## After v0.1

Open issues for v0.2:

- [ ] Parallel steps with quorum rules
- [ ] Conditional steps (`when()`)
- [ ] Spatie Permission integration (`role:` resolver)
- [ ] Relationship-based approvers (`relation:` resolver)
- [ ] Delegation
- [ ] Escalation + deadlines + scheduled command
- [ ] Resubmit after rejection
