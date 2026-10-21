# SUPERKING LUBRICANTS website

Responsive PHP / MySQL brand website and protected CMS for Royal Myanmar King Company Limited. The public design follows the supplied HTML concept: yellow #FFD130, red #C1121F, charcoal #1F1F1F, alternating sections and angled accents. The logo is extracted from that concept. This is a local XAMPP implementation; no external deployment has been performed.

## Run locally

1. Start Apache and MySQL in XAMPP.
2. On a new installation, copy .env.example to .env and configure the database.
3. Run the migration and seed commands below.
4. Open http://localhost/superking-website/ and http://localhost/superking-website/admin.

~~~powershell
php bin/console migrate
php bin/console db:seed
~~~

The current workspace migrations have been applied. Existing company records and uploads are retained. Recognizable starter demo products, contacts, slides and company sections are moved to draft. The default seed fills every business module with clearly labelled demonstration data. Sample products, contacts, technical articles, dealers, documents and manufacturing records remain draft/unverified. Only the company-history section drawn from the supplied brief is published. No genuine product photograph, operational factory, approval or certification is invented.

A fresh seed creates admin@superking.local only if no administrator exists and prints its initial password. SEED_ADMIN_PASSWORD overrides the existing local default, password. Configure your chosen initial password in .env before setup and change sign-in details under **Your account**. Existing accounts and passwords are preserved. Local recovery: run php bin/admin-password your-email@example.com, then provide the new password through stdin.

To reset a development database completely:

~~~powershell
php bin/console migrate:fresh
php bin/console migrate:fresh --seed
~~~

This permanently removes **every table, view and record** in the configured DB_DATABASE, including admin accounts and objects not tracked by migrations, then reruns all migrations. It works with forward-only migrations and creates the database if it does not exist. Without --seed, application settings and administrator accounts are not seeded. With --seed, the seeder fills the demo modules and prints the initial administrator password. Use --seed --minimal for just settings, categories and the first admin account. Uploaded files are retained; this command does not restore backups.

The command displays the database/server and asks you to type the exact database name. Cancellation or EOF leaves the database unchanged. For intentional automated resets, use:

~~~powershell
php bin/console migrate:fresh --seed --force
~~~

--force bypasses the confirmation. Check DB_DATABASE and back up any data you need before resetting. Unknown options and MySQL system databases are rejected.

For PHP's development server:

~~~powershell
php -S 127.0.0.1:8000 -t public public/router.php
~~~

No Node build is needed. Keep APP_URL empty for local automatic path detection. Production needs its full HTTPS URL, APP_DEBUG=false, a unique session name and public/ as the document root.

## Module seeding

~~~powershell
php bin/console migrate
php bin/console db:seed
~~~

The demo profile supplies the following baseline records on a fresh database:

| Table / module | Records | State |
| --- | ---: | --- |
| settings | 1 | Initial company settings, only when missing |
| admins | 1 | Initial admin, only when no account exists |
| categories | 5 | Bilingual product categories |
| products | 15 | Draft, illustrative grades/sizes; no API/OEM claims |
| slides | 2 | Draft banners with working catalogue/distributor links |
| company_sections | 3 | Approved brief history published; two drafts |
| contacts | 3 | Draft departments; reserved example.invalid emails |
| articles | 9 | Seven knowledge topics and two news drafts; technical review pending |
| manufacturing_updates | 3 | Draft/unverified planning, installation and commissioning templates |
| dealers | 6 | Fictional draft locations, unverified |
| documents | 4 | Draft PDFs clearly marked DEMO / NOT APPROVED |
| inquiries | 8 | All four inquiry types and all three handling statuses |
| admin_activity | 9 | Clearly identified seeder activity linked to seeded records |

Migration history is populated by the migration runner. Login attempts and inquiry rate-limit tables stay empty until the application needs them; seed data does not lock out users or consume spam limits.

Seed definitions live in database/seeders/, coordinated by database/seed.php. Stable seed_key values keep reruns from duplicating renamed records or replacing staff edits, approvals, dates, notes, company settings or credentials. Missing sample records are recreated, while existing records remain unchanged. The seed runs in a database transaction. Product categories, related articles, inquiry handlers and activity records use actual database IDs rather than hard-coded ID assumptions.

Use the minimal profile for initial setup without demonstration records:

~~~powershell
php bin/console db:seed --minimal
php bin/console migrate:fresh --seed --minimal
~~~

Alternatively, set SEED_PROFILE=minimal in .env. The explicit --minimal flag overrides the environment profile. It does not delete demonstration data that already exists; only migrate:fresh resets the database.

Demo PDF fixtures live in public/assets/documents/demo/. They are training attachments, not approved company profiles, catalogues, technical documents or certificates. Regenerate them with python database/fixtures/build_demo_documents.py. They are not uploaded user files and are not deleted when database records are removed.

## Public website

- Home, About, Products, Manufacturing & Quality, Knowledge Center, Find a Dealer, Distributor, News, Contact and Privacy.
- English and Myanmar URLs (?lang=en / ?lang=my), language links, canonical links and hreflang. Static UI translations are provided; staff can edit Myanmar company/product/article content. Empty translations fall back to English.
- Catalogue search across product names/codes, categories, descriptions, applications and technical fields; combined category, viscosity, specification and vehicle/equipment filters; pagination.
- Individual products with documented technical fields, package sizes, genuine images, TDS/SDS and storage guidance. Missing images use a text notice rather than invented packaging.
- Manufacturing stage derives from the latest published, company-verified update. No operational facility, laboratory, certificate or production claim is supplied as demo content.
- Knowledge articles require a named technical reviewer before publication; separate news articles, topics and related-product links.
- Verified dealer directory with region, city/township search and optional Google Maps links.
- Distributor/export form and product, complaint/authenticity and general contact forms.
- Inquiries are stored in the admin inbox. Confirmation displays a unique reference. **Email notifications are not configured. Staff must monitor the inbox.**
- Approved company profiles, catalogues, technical documents and certificates can be uploaded as PDFs.
- Sitemap, robots, organization structured data, editable metadata/image alt text, optional Search Console token and consent-controlled GA4.

## Staff CMS

Use the existing admin login. The sidebar contains products, categories, homepage banners, company information, contacts, knowledge/news, manufacturing updates, dealers, documents, the inquiry inbox and activity history.

1. Add accurate English content and reviewed Myanmar translations.
2. Upload genuine product/facility photographs and approved PDF documents.
3. For a knowledge article, choose its topic and enter the qualified technical reviewer. News does not require a technical reviewer.
4. For manufacturing updates, choose the actual stage and confirm company verification. Do not mark planned operations as production.
5. Verify dealer records and documents before publishing. Documents need a PDF. Draft records are hidden publicly.
6. Check both languages using **View website**.
7. Open **Inquiry inbox**, search by name/reference or filter by status, open a submission, respond through confirmed company channels and update its handling status. Permanent deletion uses the confirmation dialog.

Brand & settings includes the company introduction, Myanmar introduction, SEO description, approved logo/favicon, GA4 measurement ID and Search Console verification token. Public colors follow the concept palette; the editable accent controls the admin theme. Homepage banners appear as accessible announcements below the main brand hero.

## Daily admin workflows

The Overview reads live database counts for new/in-progress inquiries and published/draft content. Each count links to its working list. Latest inquiries, recently edited products and recent staff activity are shown on the dashboard.

Content lists support literal search, publication/category/type/verification filters where applicable, sorting and pagination. Edit, cancel, save and delete preserve the list filters. Use **Save & keep editing** to save without leaving the record. **Save changes** returns to the list. Changes are not autosaved.

Products and articles have an authenticated **Preview saved content** page, including drafts. Previews are marked as staff-only and noindex. Unsaved changes do not appear there. Related products are selected from a named dropdown; deleting a product clears its optional article relationships.

Uploads show the selected image and file size before saving. Browser-side size/type checks supplement server validation. Replaced or removed files are cleaned up after the database change succeeds.

The inquiry inbox supports reference/sender/company/country search, type/status/date filters and a filtered UTF-8 CSV export. CSV fields are escaped for spreadsheet formula safety. Opening an inquiry lets staff update status, maintain private follow-up notes and see the last handling time/staff member. Status-only updates preserve notes. Email and phone links open the staff member's applications; no email is sent automatically.

**Activity history** records who created, updated or deleted content, changed settings/account details or handled inquiries. Passwords and inquiry messages are not copied into activity records.

Under **Your account**, enter the current password to change the email. Leave both new-password fields empty to retain the existing password, or enter matching new passwords of at least 12 characters. Signing out ends the admin session.

## Security and operations

Admin access uses password hashes, sessions and login throttling. Mutations require CSRF tokens. Forms have server-side validation, a honeypot, minimum completion time, hourly limits and transactional storage. Inquiry data is escaped in the inbox and pages carrying session/form information send Cache-Control: no-store. No form sends a message to an external service.

Uploads accept verified JPEG/PNG/WebP images (5 MB) and PDF files (10 MB) with randomized names. Replaced/removed uploads are cleaned up. HTML/SVG/script uploads are rejected. Rich text is sanitized. Database queries use prepared bindings.

Back up the database, public/uploads, application source and protected .env together, before changes and on a daily production schedule. Keep backups outside the web root, with restricted access and encrypted offsite copies. Example database backup:

~~~powershell
& D:/xampp/mysql/bin/mysqldump.exe --user=root --single-transaction --default-character-set=utf8mb4 --result-file=D:/backups/superking.sql superking_website
~~~

Use the configured database user and password prompt as needed. Restore into a separate empty database, restore matching uploads/source, point a separate environment at it, run migrations and verify content/sign-in before switching production. Migrations added on October 20 are intentionally forward-only so rollback cannot discard customer inquiries. Recovery uses a matched backup. Test recovery regularly.

Production hosting must configure HTTPS, scheduled backups, log rotation, uptime/error monitoring, least-privilege database access and supported PHP/MySQL updates. Review and delete old inquiries through the CMS according to an approved retention policy.

## Verification

~~~powershell
python -m pip install requests playwright
python tests/specification.py
python tests/browser.py
python tests/product_search.py
python tests/migrate_fresh.py
python tests/seeding.py
~~~

The specification suite creates a uniquely named disposable database, runs migrations/seed, starts a local PHP server, validates CMS workflows, upload rejection, review/verification gates, translations, catalogue filters, inquiry persistence/status/deletion, CSRF, spam limits and sitemap behavior, then checks responsive layouts and admin theme/density in system Microsoft Edge. It removes its own database and server afterward. It never changes the company admin password or company database.

The integration entry point invokes the same isolated suite. Browser and search scripts are read-only checks against SK_TEST_URL (default XAMPP URL). Browser screenshots go to ignored storage/qa/.

Public body text uses 17 px on desktop and 16 px on mobile; navigation uses 15-16 px, with 14 px minimum supporting text. Section padding is 40 px on desktop and 28 px on mobile. Category cards without photographs no longer reserve a blank image area. Admin table/form text is enlarged while keeping compact layouts.

The public pages have been checked in English and Myanmar at 1920, 1440, 1280, 1100, 1024, 768, 390 and 320 px, including typography, spacing, navigation, search/reset/Back and page overflow. The in-app browser connector failed at initialization in this environment; browser checks used system Edge through Playwright.

Local checks do not establish field Core Web Vitals. Before launch, measure mobile Lighthouse/PageSpeed and image sizes; monitor Search Console field LCP <= 2.5 s, INP <= 200 ms and CLS <= 0.1 at the 75th percentile when enough traffic exists.

See [the specification handover](docs/implementation-handover.md) for company material and hosting/account work still needed before launch.

### Publish the demonstration content

After `php bin/console db:seed`, run `php bin/console content:publish-demo` to make all seeded public content visible. This publishes 44 records across products, banners, company sections, contacts, articles, manufacturing updates, dealers and documents. It changes only rows with a `demo.` seed key and fills missing photos without replacing existing images. Repeating it is safe; seeding again preserves publication choices.

Published demos keep their sample titles, the site displays a bilingual demonstration notice, and stock application photos carry captions. Demo knowledge guides do not imply technical review; demonstration dealers and PDFs remain unverified. Verified manufacturing progress still derives exclusively from company-verified updates. Normal CMS records retain their reviewer/verification publication requirements. Demo records can be edited, unpublished and republished in the admin; create a new record for approved company content. Private inquiries, activity, settings and administrator credentials are never published by this command.

Five locally hosted WebP application photos are included in `public/assets/images/applications/` (approximately 415 KB total). Sources: [motorcycle maintenance - Gera Cejas](https://www.pexels.com/photo/27704018/), [vehicle servicing - Anna Shvets](https://www.pexels.com/photo/4315574/), [commercial transport - Ahmet Polat](https://www.pexels.com/photo/5410923/), [industrial machinery - Roshan Ravi](https://www.pexels.com/photo/32900156/), and [gears - Connor Lucock](https://www.pexels.com/photo/28752154/), used under the [Pexels license](https://www.pexels.com/license/). They illustrate application contexts and are not SUPERKING packaging, personnel, facilities or a dealer location.
