# TrustGrid Africa

TrustGrid Africa is an open-source digital trust and security management platform for African organisations. This repository now includes a PHP 8.2/MySQL application with a session-authenticated JSON REST API and a responsive, organisation-scoped workspace.

## Features

- Organisation and administrator registration; role-aware access (`admin`, `analyst`, `contributor`, `viewer`).
- Secure sign-in, session rotation, CSRF protection and per-IP authentication throttling.
- Private organisation dashboard with asset, assessment and finding metrics.
- Authorised asset inventory for websites, applications, APIs, domains, databases and servers.
- User-triggered, non-invasive website reviews for HTTPS/TLS, common security headers, homepage cookies and HTTP-to-HTTPS redirection; public API endpoint reviews for HTTPS/TLS, response headers, JSON content type and cross-origin policy.
- Guided evidence-based assessments with original plain-language questions mapped at a high level to NIST CSF 2.0, CIS Controls v8.1, ISO/IEC 27001:2022 themes, OWASP web application themes, and a Malawi/community organisation baseline.
- Asset-specific API and database team-review questions that turn access, data protection, update, backup and recovery gaps into prioritised follow-up actions.
- Automatic finding creation from failed Website/API review checks, with repeat reviews resolving old open scanner findings before adding current issues.
- Asset-specific finding filters and asset removal for authorised workspace roles.
- Manual assessment recording and risk score history for other asset types.
- Finding management with severity, evidence notes, remediation recommendations and status tracking.
- Downloadable PDF reports for organisation security overviews and completed guided assessments, plus browser printing.
- Responsive visual identity with locally hosted, original SVG artwork.
- REST API endpoints under `/api`.

Scheduled or continuous scanning, crawling, exploit testing, email verification/password recovery, team invitations, and the community knowledge base are not enabled in this MVP. Registering an asset never triggers a review; an authorised user must explicitly start one.

## Requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `openssl`, and `curl` enabled.
- MySQL 8.0+ (the schema uses InnoDB and `utf8mb4`).
- Apache with `mod_rewrite` and `.htaccess` enabled, or PHP's built-in server for local development.

The project is configured for the WAMP layout used during development. Update the executable paths below if your WAMP version differs.

## Local WAMP setup

1. Start Apache and MySQL from the Wampserver tray menu.
2. Create the database and an application-specific account from the MySQL administrator account (replace the database password):

   ```sql
   CREATE DATABASE trustgrid_africa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'trustgrid_app'@'127.0.0.1' IDENTIFIED BY 'use-a-unique-long-database-password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON trustgrid_africa.* TO 'trustgrid_app'@'127.0.0.1';
   FLUSH PRIVILEGES;
   ```

3. Select `trustgrid_africa` in phpMyAdmin and import [`database/schema.sql`](./database/schema.sql). The schema deliberately does not create databases or users, so the runtime account needs access only to this database.
4. Copy the example configuration, then generate a unique application key in PowerShell:

   ```powershell
   Copy-Item .\config\local.php.example .\config\local.php
   $bytes = New-Object byte[] 32
   $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
   $rng.GetBytes($bytes)
   [Convert]::ToBase64String($bytes)
   ```

   Put the resulting string in `app_key`, then set the local MySQL host, database name, user and password. `config/local.php` is ignored by Git and blocked from direct Apache access.
5. Open `http://localhost/TrustGrid%20Africa/` and create the first organisation workspace.

### Existing deployment migration

Before deploying this feature to an existing installation, back up the database and import [`database/migrations/20261106_add_safe_web_reviews.sql`](./database/migrations/20261106_add_safe_web_reviews.sql) using an account allowed to alter tables and create tables. The migration adds safe-review assessment types and result/throttle tables. The application database account itself does not need schema-change privileges.

Guided assessments require [`database/migrations/20261106_add_guided_assessments.sql`](./database/migrations/20261106_add_guided_assessments.sql). Back up the database and apply it with the same schema administrator before deploying the guided-assessment application code.

Authorised engagement records require [`database/migrations/20261107_add_assessment_engagements.sql`](./database/migrations/20261107_add_assessment_engagements.sql), applied after the guided-assessments migration. This adds an organisation-scoped engagement register, optional guided-assessment links and the Cancelled draft state.

If Apache does not route `/api/...` to `api/index.php`, enable `mod_rewrite`, allow overrides for the project directory, and restart Apache.

### PHP built-in server (local development only)

Set the same database configuration in `config/local.php`, then run:

```powershell
Set-Location 'C:\wamp64\www\TrustGrid Africa'
$env:APP_KEY = 'your-generated-unique-secret-of-at-least-32-characters'
& 'C:\wamp64\bin\php\php8.2.29\php.exe' -S 127.0.0.1:8081 -t .
```

Visit `http://127.0.0.1:8081/`. Use HTTPS for any deployed environment; configure a TLS certificate and secure database credentials before deployment.

## REST API

All responses use JSON shaped as `{ "data": ... }` or `{ "error": { "message": ... } }`. Authenticated write requests require the CSRF token from `GET /api/auth/me` in the `X-CSRF-Token` header. The web client obtains the token from the session page.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/auth/register` | Create an organisation and its first admin |
| POST | `/api/auth/login` | Sign in |
| POST | `/api/auth/logout` | Sign out |
| GET | `/api/auth/me` | Current user and CSRF token |
| GET | `/api/dashboard` | Organisation-scoped counts, trend and recent items |
| GET, POST | `/api/assets` | List or register authorised assets |
| GET | `/api/assets/{id}/scan` | Read the latest safe Website/API review |
| POST | `/api/assets/{id}/scan` | Start a bounded Website/API review (write role, CSRF, authorization confirmation required) |
| PATCH | `/api/assets/{id}` | Update an asset status |
| DELETE | `/api/assets/{id}` | Permanently remove an asset and its related findings, reviews and history |
| GET | `/api/assessments/catalog` | Read the available original assessment questions and baseline themes |
| GET, POST | `/api/assessments/engagements` | List or record organisation-scoped authorised engagement windows |
| PATCH | `/api/assessments/engagements/{id}` | Complete or cancel an open engagement |
| GET, POST | `/api/assessments/guided` | List or start a guided evidence-based assessment |
| GET | `/api/assessments/guided/{id}` | Read an organisation-scoped assessment and its responses |
| POST | `/api/assessments/guided/{id}/answers` | Save draft responses |
| POST | `/api/assessments/guided/{id}/cancel` | Cancel a draft without generating findings |
| POST | `/api/assessments/guided/{id}/complete` | Complete an assessment and create prioritised follow-up findings |
| POST | `/api/assessments` | Record a manual review and 0–100 risk score |
| GET | `/api/findings?asset_id={id}` | List findings, optionally filtered to one organisation asset |
| POST | `/api/findings` | Record a manual finding |
| PATCH | `/api/findings/{id}` | Update an organisation finding's status |

The Reports page downloads an organisation-scoped security overview PDF from `/report-pdf.php`. Completed guided assessments have a separate detailed PDF download at `/assessment-pdf.php?id={id}`. Both downloads require a signed-in session and include only data in that user's organisation.

Asset creation requires explicit authorisation confirmation and a note describing ownership or permission. API records are scoped to the signed-in user's organisation.
Manual assessment scores map to risk indicators as follows: 0–34 Low, 35–59 Moderate, 60–79 High, and 80–100 Critical.

Automatic reviews are available only for authorized, active Website and API assets. They are manually triggered and throttled to one request per asset per minute. Website review makes bounded requests to the public homepage; API review makes one bounded GET request to the configured HTTPS endpoint (standard port, no query string or fragment). Neither follows redirects, crawls, submits forms, stores or inspects response bodies, nor sends test or exploit payloads. Reviews check TLS validation and selected response settings; API checks also report JSON content type and flag wildcard cross-origin policy for human review. Private/reserved IP resolutions are rejected and connections are pinned to a resolved public IPv4 address. These read-only checks do not test API authentication or authorization and are not a penetration test.

Database assets are reviewed with an organisation-shared guided checklist covering least-privilege access, network boundaries, encryption, key handling, updates, activity review, backups and restoration. The application does not connect to, authenticate against, or probe a database host. Teams should confirm answers with the responsible administrator and record only non-sensitive evidence; never enter credentials, keys, connection strings or personal data. Guided assessments and automated checks create prioritised follow-up items, but their scores are indicators rather than independent verification, certification or a guarantee that an asset is secure.

Failed Website/API review checks are added as findings automatically. A later review resolves prior open/in-progress scanner-generated findings before creating findings for any remaining issues; manually recorded findings are not changed. Removing an asset permanently deletes its findings, review results and assessment history.

Guided assessments use original plain-language prompts and support one or more of six high-level baselines, including a dedicated Database & data resilience baseline. Mappings indicate related themes, not official clause-by-clause compliance. The maturity score is a weighted self-reported percentage (implemented = 100%, partial = 50%, not in place/not sure = 0%; not applicable is excluded). Gaps create follow-up findings; a later guided assessment resolves prior findings only for the controls it reassesses before adding current results. “Not sure” creates a verification action and is not treated as a confirmed vulnerability. The score is not a certification or independently verified audit.

Engagement records capture the authorised asset, named lead, written authorisation reference, in-scope and excluded work, rules of engagement and inclusive start/end dates. Only write-role users can create or close records; guided assessments can link only to an open engagement for the same organisation and asset during its approved date window. Saving responses is blocked if a linked engagement closes or expires. Completed assessment PDFs include linked engagement details. Keep approval evidence in your organisation's approved records system; the engagement register is not a substitute for written permission, and it does not expand the safe, read-only automated checks into active testing.

## Security notes

- Use HTTPS in production; the session cookie is `HttpOnly`, `SameSite=Lax`, and marked `Secure` under HTTPS.
- Configure a unique random `APP_KEY` and a database user limited to the application's database.
- Sign-in attempts are limited to 8 per IP per 15 minutes; workspace registration is limited to 3 per IP per hour.
- Add email verification, account recovery and operational monitoring before making self-service registration widely available.
- Passwords use PHP's `password_hash`; database operations use PDO prepared statements.
- Content Security Policy and additional browser security headers are emitted by the application.
- Findings/evidence are private to the organisation. Never place passwords, API keys or other secrets in evidence notes.
- Risk scores are explainable monitoring indicators, not formal security certifications.
- Do not review assets without explicit authorisation. Automated reviews are deliberately limited to bounded, read-only endpoint configuration checks.
