# Mobile citizen alert submission

- **Objective:** Add the mobile-only citizen flow to compose an alert with a type and description.
- **Problem:** The Flutter app is still the generated counter demo; the Laravel starter has no alert API.
- **Why:** Enable the requested mobile experience while web/API work remains explicitly out of scope.
- **Scope:** Flutter form, basic required-field validation, local confirmation only, focused widget tests.
- **Constraints:** Do not change `web/`; do not imply that alerts are remotely persisted or transmitted. Leave backend integration for later. Preserve existing `.atl/` user data.
- **Delivery strategy:** ask-on-risk.
- **Forecast:** Less than 400 authored changed lines.
- **Engram mirror:** Pending; current host session is not registered and project lookup for `p01-alertas-ciudadanas` is unavailable.

## Tasks

- [ ] M1 (delegated): Replace Flutter demo with accessible citizen alert form and local submission confirmation; add widget tests.
  - **Route:** Delegated direct; multiple non-trivial implementation/test files and preparation for edits.
  - **Authorized edit surfaces:** `movil/lib/main.dart`, `movil/test/widget_test.dart`.
  - **Acceptance:** Type and description are required; valid submit provides only local confirmation and resets; no backend or web edits.
  - **Checks:** `flutter analyze`; `flutter test` (record actual results).
  - **Review:** RDD on; assess writer diff; follow native status/consent when applicable.
  - **Commit:** Pending.

## Progress

- Read-only exploration confirmed there is no current alert taxonomy or API. The mobile UI must avoid claiming server delivery.

## Next step

- Implement and verify M1, then update this document and Engram mirror if available.
