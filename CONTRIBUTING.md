# Contributing to Approvio

Thanks for your interest in contributing! Approvio is an open-source Laravel
approval workflow package and we welcome PRs, issues, and discussion.

## Getting started

```bash
git clone https://github.com/enad/approvio.git
cd approvio
composer install
vendor/bin/pest
```

If all tests pass, you're set up.

## What we accept

- **Bug fixes** with a regression test.
- **New approver resolvers**, **tenant resolvers**, or **strategies** —
  these are the primary extension points and the package thrives on them.
- **Documentation improvements**, including small typos.
- **Test coverage** for under-tested paths.

For larger features (parallel steps, DB workflows, UI packages), please open
an issue first to discuss the approach.

## Coding standards

- PSR-12 + Laravel conventions.
- `declare(strict_types=1);` at the top of every PHP file.
- PHPStan must pass at the level configured in `phpstan.neon`.
- All new code must have Pest tests.
- Public APIs need docblocks; internal helpers don't.

## Pull request checklist

Before opening a PR:

1. `vendor/bin/pest` — all tests green.
2. `vendor/bin/phpstan analyse` — no errors.
3. Update `CHANGELOG.md` under `[Unreleased]`.
4. Keep PRs focused. One concern per PR.

## Reporting bugs

Open an issue with:

- The Laravel + PHP version you're on.
- The minimum reproduction (a failing test case is ideal).
- What you expected vs what happened.

## Security

If you discover a security issue, **please do not open a public issue**.
Email the maintainer directly.
