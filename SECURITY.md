# Security operations

## Production baseline

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the public HTTPS URL, `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, and `REQUIRE_MALWARE_SCAN=true`. Terminate TLS with a currently supported TLS configuration and ensure the proxy forwards the original scheme. Install ClamAV and keep its signatures updated before enabling uploads.

Uploads must be validated with Laravel file rules for an explicit MIME allowlist and size limit, stored outside `public`, assigned a generated name, and passed to `App\Security\MalwareScanner::assertClean()` before persistence. The application currently has no user-facing file-upload route.

## Backup and recovery

Create encrypted daily database backups and encrypted weekly copies of persistent storage. Keep at least one copy in a separate account or region, restrict restore access, and alert on missed jobs. Never place database credentials or backups in Git.

Every quarter, restore the newest backup into an isolated environment, run migrations in pretend mode, verify row counts for users, students, enrollments, attendance events, and audit logs, then perform login and attendance smoke tests. Record the backup identifier, restore duration, checks performed, result, and owner. A backup is not considered healthy until this restore drill passes.

## Release gate

Before each major release: run PHPUnit, production frontend builds, `composer audit --locked`, both npm audits, a DAST scan against staging, and an authenticated penetration test covering RBAC, MFA, session expiry, IDOR, CSRF, XSS, SQL injection, uploads, and device-token abuse. Block release on unresolved critical or high findings. Re-test fixes and retain the signed report.

## Outbound links

Only publish outbound HTTPS links to approved domains. Add `utm_source=attendance_portal`, `utm_medium=referral`, and a feature-specific `utm_campaign` to campaign links. Review reverse-proxy egress logs monthly for unexpected destinations and alert on application-server connections outside the mail provider, database, dependency registries used by CI, and explicitly approved integrations. The authenticated portal currently exposes no general-purpose outbound-link or redirect feature.

## Incident response

Revoke affected Sanctum tokens and web sessions, rotate exposed credentials and `APP_KEY` when appropriate, preserve application and proxy logs, identify affected records through `audit_logs`, notify the system owner, and document containment, recovery, and follow-up actions.
