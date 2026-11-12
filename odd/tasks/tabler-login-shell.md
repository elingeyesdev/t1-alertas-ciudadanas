# Tabler login and initial shell

## Objective
Add an admin-only session login and a minimal Tabler shell alongside React. Preserve the public landing, existing API/JWT contracts, mobile app and existing /alertas route. Do not migrate business modules.

## Scope and approach
- New /panel/login, /panel and POST /panel/logout routes; explicit web guard and admin authorization.
- Locally bundled Tabler assets through backend Vite; no React/Tailwind imports for these pages.
- Reuse existing users, phone/password and roles. Login throttling, session regeneration, CSRF, safe redirects and logout invalidation.
- Existing Spanish UI conventions apply. No secrets, real database changes or deployment.

## Tasks
- [ ] T1: Implement and verify login and Tabler shell as one coherent work unit.
  - Route: delegated direct; authentication, views, assets and tests require multiple non-trivial files.
  - Checks: deterministic isolated auth feature tests RED then GREEN where runnable; PHP syntax, route list, asset build, API regression and UI smoke where environment permits.
  - Acceptance: guests redirect; only admins enter; invalid login denied; CSRF/throttle/remember/logout covered; API remains JSON/JWT; shell contains no fake working business modules.
  - Implementation present; commit pending delivery-strategy choice and native review consent.
  - Writer: SQLite in-memory suite PASS (5 tests, 53 assertions), Pint/PHP lint/diff checks PASS. Includes session/CSRF rotation, remember-cookie restoration, login/logout CSRF, lockout and admin role checks.
  - Independent verifier: earlier suite PASS (5 tests, 41 assertions), asset build and routes PASS; Edge guest login smoke at 390x844 and 1440x900 PASS, zero console errors. Later strengthened tests await parent spot-check completion.
- [ ] T2: Independently verify candidate and complete native review when enabled; record actual results and remaining limitations.

## Delivery and verification
- Current feature branch: feature/29-CI/CD-docker. No push, PR or merge authorized.
- Delivery strategy: ask-on-risk. Initial forecast: approximately 350–400 authored lines, excluding generated dependency lockfile; revise honestly after implementation.
- Base boundary: 7c856ea7e1b5a2169ee9971f6d1c428eced957d9.
- RDD: on (global). Exact native transitions govern review consent.
- Environment: backend vendor and node_modules absent; CLI PDO SQLite available, PostgreSQL driver absent. Never execute destructive test lifecycle against existing PostgreSQL configuration. Prefer process-local SQLite in-memory override after schema feasibility check.
- Generated Composer lock resolves Laravel 13.42.0; dependencies installed locally. Tabler assets built successfully; optional fontaine warning does not fail build.
- A reversible nullable remember_token migration was required and added as source only. It has NOT been applied to the real database; deployment requires explicit migration execution by the operator.
- Authored source is now approximately 424 additions/deletions excluding dependency locks and this document; ask-on-risk chain strategy choice is pending before commit. No PR/push is authorized.
- Full API/JWT regression, authenticated shell browser smoke and PostgreSQL migration execution are not verified. Existing API guest JSON response is covered.

## Next step
Obtain delivery chain choice (stacked-to-main or feature-branch-chain), finish spot check and native review consent lifecycle, then record a work-unit commit. Keep T1/T2 unchecked until required outcomes are actually closed.
