# Laravel and PostgreSQL containers

## Objective and boundaries
Prepare production and local Docker environments for the existing Laravel API and Blade/Tabler panel, backed by containerized PostgreSQL. Preserve pending Tabler work, React and mobile. No remote deployment, real database migration, credential reuse or destructive Docker cleanup.

## Design
- Shared Compose definition plus explicit local and production overrides. Separate project names/volumes and environment files prevent cross-environment data reuse.
- Production image bundles Composer dependencies and Vite assets; no source bind mounts, development dependencies, secrets or debug output.
- PostgreSQL is internal, healthchecked and persisted. Uploads persist separately. Migrations/seeding are explicit operator actions, never automatic at startup.
- Local source mounts and Vite hot reload with isolated dependency volumes. Production-style local smoke is allowed using disposable project volumes, never existing databases.
- Single Laravel service serves both API and Tabler; React remains separate. Reverb is not required for this first login/shell module; document realtime limitation rather than silently promise live maps.

## Tasks
- [ ] D1: Implement image, Compose variants, nonsecret environment templates and operating instructions.
  - Route: delegated direct (multiple configuration files and environment integration).
  - Acceptance: local/prod render; production defaults fail closed for missing credentials, image build excludes secrets, PostgreSQL health gating and named persistence, no automatic migrations.
- [ ] D2: Independent configuration checks and image/runtime smoke where Docker daemon permits.
  - Verification: Compose config for both environments, Docker build/check, isolated health/login/assets/DB smoke where feasible; no claim of server deployment.
  - Implementation and independent verification passed; commit/native review pending. Preserve earlier Tabler implementation; do not accidentally include browser artifacts.

## Delivery
- Forecast approximately 300–400 authored lines excluding dependency locks. Delivery strategy ask-on-risk; no chain strategy has been chosen for prior Tabler work. No push/PR/merge authorized.
- Existing branch feature/29-CI/CD-docker. Prior Tabler code and lockfiles are uncommitted and must be preserved; new images depend on their presence.
- RDD previously on; native consent remains candidate-specific.

## Checks and next step
Both Compose configurations and Docker build checks passed with test-only secrets. Independent production/local image builds passed. Fresh isolated PostgreSQL migrations, SELECT 1, login HTTP 200, API unauthenticated JSON 401, uploads writable by www-data and session persistence across app recreation passed in both modes. Local HMR custom port localhost:15173 and OPcache disabled/timestamp validation enabled passed after correcting INI load order. A custom-format PostgreSQL backup restored accented text correctly into a separate disposable database.

Production image: sha256:5a566e967cc7eac8fe2346787f6d159d06e1e315ae3064f0a18c7845a73e942f. Corrected local image: sha256:e7bbfb69f6e814217b865e7fae6ea368b5e087d69c9b1fcd9896c86815cd43ff. Final Dockerfile change only altered local-stage INI filename; production stage unchanged.

All smoke containers in tabler-docker-smoke-prod/local stopped; their disposable volumes retained. No existing database or remote server touched. Application TLS/proxy deployment and browser authentication restoration after replacement remain unverified. Persistent bootstrap manifests refresh at startup to prevent provider-cache contamination. Source config approximately 245 lines plus documentation; combined earlier Tabler work exceeds 400-line delivery heuristic. Chain strategy, commits and native review consent remain pending. D1/D2 stay unchecked until required closure is recorded.

Next: native candidate review consent and delivery strategy; production requires private keys/passwords, proxy/TLS and explicit operator-run migrations. Existing public API registration role selection described in backend README is a pre-existing security concern requiring separate review before Internet exposure; Docker does not resolve application authorization policy.
