# TrustGrid Africa

TrustGrid Africa is the brainchild of **ITEC ICT SOLUTIONS**, Zomba, Malawi, created inside Billys' House, Room #5. It is an open-source digital trust and security management platform for African organisations.

| Project details | |
|---|---|
| Application version | **0.2.0 (MVP pre-release)** |
| Contributors | Leonard Mhone, Adam Wasili, Promise Henderson |
| Languages and technologies | PHP 8.2, JavaScript (ES6), HTML5, CSS3, SQL (MySQL 8.0+) |
| Application stack | PHP 8.2, MySQL 8.0+, Apache |

This version number identifies the current MVP baseline; it is not a claim of a published stable release.

## Features

- Organisation and administrator registration; role-aware access (`super_admin`, `admin`, `analyst`, `contributor`, `viewer`).
- Secure sign-in, session rotation, CSRF protection and per-IP authentication throttling.
- Private organisation dashboard with asset, assessment and finding metrics.
- A separate super-admin console for workspace approval, news/comment moderation, subscriber management and cross-platform activity review.
- Public technology news with searchable, paginated article pages, featured images, moderated comments and rich-text authoring.
- A footer newsletter opt-in with bot checks and a super-admin subscriber list; campaign delivery is not enabled until an email provider is configured.
- An on-page WhatsApp chat panel that opens a prefilled message to +265 992 919 716; replies are handled in WhatsApp on the team's phone.
- A linked sitemap grouping public information, account access and workspace tools.
- Editable user profiles with an organisation-only directory and private profile-image delivery.
- Pending-approval workspace registration, rate-limited human checks and a honeypot on public forms.
- Authorised asset inventory for websites, applications, APIs, domains, databases and servers.
- Asynchronous, non-invasive Website/API reviews with a durable queue, retryable background worker and opt-in daily/weekly schedules that expire within 90 days.
- Guided evidence-based assessments with original plain-language questions mapped at a high level to NIST CSF 2.0, CIS Controls v8.1, ISO/IEC 27001:2022 themes, OWASP web application themes, and a Malawi/community organisation baseline.
- Asset-specific API and database team-review questions that turn access, data protection, update, backup and recovery gaps into prioritised follow-up actions.
- Automatic finding creation from failed Website/API review checks, with repeat reviews resolving old open scanner findings before adding current issues.
- Asset-specific finding filters and asset removal for authorised workspace roles.
- Manual assessment recording and risk score history for other asset types.
- Finding management with severity, evidence notes, remediation recommendations and status tracking.
- Downloadable PDF reports for organisation security overviews and completed guided assessments, plus browser printing.
- Responsive visual identity with locally hosted, original SVG artwork.
- REST API endpoints under `/api`.

Continuous scanning, crawling, exploit testing, email verification/password recovery, team invitations, and the community knowledge base are not enabled in this MVP. Registering an asset never triggers a review; an authorised user must explicitly start a review or opt in to a time-limited schedule.

## Requirements

- PHP 8.2+ with `pdo_mysql`, `mbstring`, `dom`, `openssl`, and `curl` enabled.
- MySQL 8.0+ (the schema uses InnoDB and `utf8mb4`).
- Apache with `mod_rewrite` and `.htaccess` enabled, or PHP's built-in server for local development.

The project is configured for the WAMP layout used during development. Update the executable paths below if your WAMP version differs.

## Project layout

- `public/` contains PHP page entrypoints and the JSON API router. Apache rewrite rules preserve the existing clean public URLs.
- `app/` contains private PHP bootstrap, authorization, security helpers, scan worker logic and page templates.
- `config/` contains local secrets and application configuration; never commit `local.php`.
- `database/` contains the canonical schema and database administrator-run migrations.
- `tools/` contains CLI-only maintenance, bootstrap and scan-worker scripts; never expose or run these as web routes.
- `assets/css/` and `assets/js/` contain the shared frontend stylesheet and application script; `assets/images/` contains site artwork.
- `uploads/` contains protected user/news images, served through controlled PHP endpoints.
- `screenshots/` holds the UI screenshots embedded in this guide.

The repository root is kept for project metadata and Apache configuration. Direct web requests to `app/`, `api/`, `config/`, `database/`, `tools/`, `uploads/`, and `public/` are denied; only explicitly routed public endpoints are exposed. Enable Apache `mod_rewrite` and `.htaccess` overrides for this protection and for route compatibility.

The public [sitemap](./public/sitemap.php) groups links to public pages, account access, workspace tools and (for super administrators) the administration console.

## Screenshots

### Homepage

![TrustGrid Africa homepage](screenshots/homepage.png)

### Organisation workspace signup

![Workspace signup page](screenshots/workspace%20signup.png)

### Sign-in

![Sign-in page](screenshots/Signin%20page.png)

### Asset dashboard

![Asset dashboard](screenshots/assets%20dashboard.png)

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
5. Open `http://localhost/TrustGrid%20Africa/` and request an organisation workspace.

6. To provision a super administrator on a new installation after schema setup, run the CLI-only bootstrap once:

   ```powershell
   & 'C:\wamp64\bin\php\php8.2.29\php.exe' .\tools\bootstrap-super-admin.php
   ```

   The command prompts for the administrator's name and email and prints a randomly generated temporary password once. Store it securely, sign in, and change it immediately. This account can approve pending workspaces and manage news. Workspace creators cannot sign in until a super administrator approves their organisation. Do not run this bootstrap if a super administrator already exists.

### Existing deployment migration

Before deploying this feature to an existing installation, back up the database and import [`database/migrations/20261106_add_safe_web_reviews.sql`](./database/migrations/20261106_add_safe_web_reviews.sql) using an account allowed to alter tables and create tables. The migration adds safe-review assessment types and result/throttle tables. The application database account itself does not need schema-change privileges.

Guided assessments require [`database/migrations/20261106_add_guided_assessments.sql`](./database/migrations/20261106_add_guided_assessments.sql). Back up the database and apply it with the same schema administrator before deploying the guided-assessment application code.

Authorised engagement records require [`database/migrations/20261107_add_assessment_engagements.sql`](./database/migrations/20261107_add_assessment_engagements.sql), applied after the guided-assessments migration. This adds an organisation-scoped engagement register, optional guided-assessment links and the Cancelled draft state.

Platform administration, profiles, news and moderated comments require [`database/migrations/20261107_add_platform_admin_news_profiles.sql`](./database/migrations/20261107_add_platform_admin_news_profiles.sql), applied after the assessment-engagement migration. It adds the `super_admin` role, pending/rejected workspace states, profile and news tables, audit logs, and public-form rate limits. Back up the database before applying it.

The newsletter opt-in and admin subscriber list require [`database/migrations/20261107_add_newsletter_subscribers.sql`](./database/migrations/20261107_add_newsletter_subscribers.sql), applied after the platform-admin/news migration.

Asynchronous Website/API reviews and recurring schedules require [`database/migrations/20261107_add_scan_queue_scheduling.sql`](./database/migrations/20261107_add_scan_queue_scheduling.sql), applied after the safe Website/API review migration. Apply it using a database administrator, not the application database account.

If Apache does not route `/api/...` to `public/api/index.php`, enable `mod_rewrite`, allow overrides for the project directory, and restart Apache.

### Background scan worker

The web app only queues explicitly requested reviews. To process jobs and due schedules, configure the CLI worker to run once per minute on the trusted application host. On Windows Task Scheduler, use the PHP executable as the program, `"C:\wamp64\www\TrustGrid Africa\tools\process-scan-queue.php" --max-jobs=5` as the arguments, and the repository root as the working directory. On Linux, configure an equivalent cron task. Do not expose the worker as a web endpoint.

The worker queues due schedules and handles up to five jobs per run. Jobs have leases so an interrupted worker can recover them, and transient failures receive at most three attempts. A scan schedule must be explicitly authorized by a write-role user, can run daily or weekly, and must have an end date within 90 days. Pausing a schedule cancels its queued scheduled jobs; a currently running bounded request may finish. Manual and scheduled reviews remain limited to authorized, non-archived Website/API assets and are throttled per asset.

### PHP built-in server (local development only)

Set the same database configuration in `config/local.php`, then run:

```powershell
Set-Location 'C:\wamp64\www\TrustGrid Africa'
$env:APP_KEY = 'your-generated-unique-secret-of-at-least-32-characters'
& 'C:\wamp64\bin\php\php8.2.29\php.exe' -S 127.0.0.1:8081 -t . tools\dev-router.php
```

The development router maps existing public URLs to `public/` and serves only approved static asset folders.

Visit `http://127.0.0.1:8081/`. Use HTTPS for any deployed environment; configure a TLS certificate and secure database credentials before deployment.

## REST API

All responses use JSON shaped as `{ "data": ... }` or `{ "error": { "message": ... } }`. Authenticated write requests require the CSRF token from `GET /api/auth/me` in the `X-CSRF-Token` header. The web client obtains the token from the session page.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/auth/register` | Request an organisation workspace; new workspaces require super-admin approval |
| POST | `/api/auth/login` | Sign in |
| POST | `/api/auth/logout` | Sign out |
| GET | `/api/auth/me` | Current user and CSRF token |
| GET | `/api/public/challenge` | Obtain a session-backed human-check challenge for public forms |
| POST | `/api/newsletter/subscribe` | Record an explicit newsletter opt-in |
| GET | `/api/news` | Search and page through published public articles |
| GET | `/api/news/{slug}` | Read a published article and its paginated approved comments |
| POST | `/api/news/{slug}/comments` | Submit a comment for moderation |
| GET | `/api/profile` | Read the signed-in user's profile |
| POST | `/api/profile` | Update profile details or change the account password |
| POST | `/api/profile/avatar` | Upload a private profile image |
| GET | `/api/profiles` | Browse active profiles in the signed-in user's organisation |
| GET | `/api/admin/summary` | Read platform-wide summary counts (super admin only) |
| GET | `/api/admin/workspaces` | List paginated workspace requests/statuses (super admin only) |
| POST | `/api/admin/workspaces/{id}/decision` | Approve or reject a pending workspace (super admin only) |
| GET | `/api/admin/comments` | List paginated comments by moderation status (super admin only) |
| POST | `/api/admin/comments/{id}/decision` | Approve or reject a pending comment (super admin only) |
| GET | `/api/admin/subscribers` | List paginated opted-in or unsubscribed addresses (super admin only) |
| POST | `/api/admin/subscribers/{id}/unsubscribe` | Remove an address from the active subscriber list (super admin only) |
| GET, POST | `/api/admin/news` | List articles or create one (super admin only) |
| GET, PATCH, DELETE | `/api/admin/news/{id}` | Read, update or delete an article (super admin only) |
| POST | `/api/admin/news-image` | Upload an article featured image (super admin only) |
| GET | `/api/admin/logs` | Browse paginated platform activity logs (super admin only) |
| GET | `/api/dashboard` | Organisation-scoped counts, trend and recent items |
| GET, POST | `/api/assets` | List or register authorised assets |
| GET | `/api/assets/{id}/scan` | Read the latest safe Website/API review |
| POST | `/api/assets/{id}/scan` | Queue a bounded Website/API review (write role, CSRF, authorization confirmation required) |
| GET | `/api/assets/{id}/scan-jobs/{job_id}` | Read the status of an organisation-scoped review job |
| GET, PUT, DELETE | `/api/assets/{id}/scan-schedule` | Read, authorize/update, or pause a daily/weekly review schedule |
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

Automatic reviews are available only for authorized, active Website and API assets. Manual requests are queued and processed asynchronously; recurring daily or weekly schedules are opt-in, require renewed authorization through a selected end date, and expire within 90 days. The CLI worker must be configured by the service operator to run regularly. Reviews are throttled to one request per asset per minute. Website review makes bounded requests to the public homepage; API review makes one bounded GET request to the configured HTTPS endpoint (standard port, no query string or fragment). Neither follows redirects, crawls, submits forms, stores or inspects response bodies, nor sends test or exploit payloads. Reviews check TLS validation and selected response settings; API checks also report JSON content type and flag wildcard cross-origin policy for human review. Private/reserved IP resolutions are rejected and connections are pinned to a resolved public IPv4 address. These read-only checks do not test API authentication or authorization and are not a penetration test.

Database assets are reviewed with an organisation-shared guided checklist covering least-privilege access, network boundaries, encryption, key handling, updates, activity review, backups and restoration. The application does not connect to, authenticate against, or probe a database host. Teams should confirm answers with the responsible administrator and record only non-sensitive evidence; never enter credentials, keys, connection strings or personal data. Guided assessments and automated checks create prioritised follow-up items, but their scores are indicators rather than independent verification, certification or a guarantee that an asset is secure.

Failed Website/API review checks are added as findings automatically. A later review resolves prior open/in-progress scanner-generated findings before creating findings for any remaining issues; manually recorded findings are not changed. Removing an asset permanently deletes its findings, review results and assessment history.

Guided assessments use original plain-language prompts and support one or more of six high-level baselines, including a dedicated Database & data resilience baseline. Mappings indicate related themes, not official clause-by-clause compliance. The maturity score is a weighted self-reported percentage (implemented = 100%, partial = 50%, not in place/not sure = 0%; not applicable is excluded). Gaps create follow-up findings; a later guided assessment resolves prior findings only for the controls it reassesses before adding current results. “Not sure” creates a verification action and is not treated as a confirmed vulnerability. The score is not a certification or independently verified audit.

Engagement records capture the authorised asset, named lead, written authorisation reference, in-scope and excluded work, rules of engagement and inclusive start/end dates. Only write-role users can create or close records; guided assessments can link only to an open engagement for the same organisation and asset during its approved date window. Saving responses is blocked if a linked engagement closes or expires. Completed assessment PDFs include linked engagement details. Keep approval evidence in your organisation's approved records system; the engagement register is not a substitute for written permission, and it does not expand the safe, read-only automated checks into active testing.

## Security notes

- Use HTTPS in production; the session cookie is `HttpOnly`, `SameSite=Lax`, and marked `Secure` under HTTPS.
- In production, disable `display_errors` and Xdebug, enable server-side error logging, and keep error logs access-controlled.
- Configure a unique random `APP_KEY` and a database user limited to the application's database.
- Sign-in attempts are limited to 8 per IP per 15 minutes; workspace registration is limited to 3 per IP per hour.
- Registration and comment forms use a session-backed human arithmetic check, minimum completion time, honeypot and per-IP rate limits. These are layered deterrents, not a replacement for a managed CAPTCHA or email verification.
- Newsletter signup also requires explicit consent, a human check and rate limiting. Subscriber addresses are private to super administrators; email campaign delivery and automated confirmation/unsubscribe email are not configured. Subscribers may request removal through the listed WhatsApp contact.
- New workspaces are pending until a super administrator approves them. Only super administrators can publish/manage news or approve/reject comments. User profile fields and photos are limited to each account; never add sensitive personal information.
- Platform activity is logged with actor, action, target and a keyed hash of the source IP. Audit logs support oversight but are not tamper-proof external compliance records.
- Add email verification, account recovery and operational monitoring before making self-service registration widely available.
- Passwords use PHP's `password_hash`; database operations use PDO prepared statements.
- Content Security Policy and additional browser security headers are emitted by the application.
- Findings/evidence are private to the organisation. Never place passwords, API keys or other secrets in evidence notes.
- Risk scores are explainable monitoring indicators, not formal security certifications.
- Do not review assets without explicit authorisation. Automated reviews are deliberately limited to bounded, read-only endpoint configuration checks.
