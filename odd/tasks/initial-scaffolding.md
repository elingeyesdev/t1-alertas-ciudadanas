# Initial scaffolding

## Objective
Create empty official Laravel and Flutter starter products for P01-alertas-ciudadanas; add concise root structure documentation.

## Problem and why
The project needs separate backend and mobile starter apps before feature work begins.

## Scope and constraints
- Use official generators with default starter content; no auth kit or domain behavior.
- Keep Flutter's internal package name `alertas_ciudadanas` under `movil/`.
- Preserve generated lockfiles; exclude local dependencies, secrets, and build outputs.
- No SDK installation, global configuration, remote publishing, or nested git repositories.

## Authorized scope
`web/`, `movil/`, `docs/`, `.github/workflows/`, `.gitignore`, `README.md`, this task document, and root `.git/`.

## Forecast and delivery
Two tasks. Generated files excluded from authored line forecast; authored scaffolding/documentation is under 400 lines. Delivery strategy: single work-unit commit.

## Route
Task 1: delegated direct; generation covers two product trees and requires command execution. Task 2: delegated direct; documentation and verification depend on generated output.

## Acceptance criteria
- Laravel and Flutter default generated projects exist in their requested directories.
- Root includes docs and workflow placeholders, a scoped ignore file, and concise English setup README.
- Required starter tests/analyzers are attempted; no generated local dependencies or secrets are tracked.
- Conventional work-unit commit recorded.

## Checks
`php artisan --version`; `php artisan test`; `flutter --version`; `flutter analyze`; `flutter test`; structural and git-status checks. Debug APK only if Android tooling is already present.

## Progress
- [x] T1 Generate official Laravel and Flutter starters.
- [x] T2 Add docs/structure and run checks; commit work unit.

## Verification evidence
- Laravel Framework 13.36.0; `php artisan --version` passed.
- `php artisan test`: passed (2 tests, 2 assertions).
- Flutter 3.47.7 / Dart 3.13.5; `flutter analyze`: no issues; `flutter test`: passed (1 test).
- `.env`, `vendor/`, and Flutter build output are ignored/untracked; git status clean after commits.
- Android debug APK skipped; no SDK installation attempted.
- `gentle-ai review mode status`: on (global). Initial assessment against empty parent failed because parent commit was absent. Retry against empty-tree SHA returned high risk (`hot_path`), review due. Exact next STATUS returned `action=start`; candidate consent is required. Parent must relay the captured consent envelope before lifecycle continuation.
- Work-unit commit: `ba46f54` (`chore: scaffold Laravel and Flutter starter apps`).

## Next step
Parent relays the exact returned START consent envelope; do not continue review without candidate-scoped user choice.

## Engram mirror
Pending: host session identity is unavailable/unregistered; do not start a memory session.
