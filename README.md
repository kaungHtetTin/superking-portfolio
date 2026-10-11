# Super King company website

Responsive PHP 8.2 / MySQL company portfolio and protected content administration, built on the existing Mini PHP framework. No Node build is required. This project runs locally on XAMPP; it has not been published to external hosting.

## Setup

1. Start Apache and MySQL in XAMPP.
2. Copy `.env.example` to `.env` on a new installation. Set your database credentials and a unique database name. The current installation uses `superking_website`.
3. Run `php bin/console migrate`, then `php bin/console db:seed`.
4. Open `http://localhost/superking-website/` and `http://localhost/superking-website/admin`.

The seed command prints a randomly generated initial password for `admin@superking.local` only when creating the first administrator. Existing records are preserved. Change the email and password under **Admin → Your account**. Passwords are stored as hashes. For local recovery, run `php bin/admin-password your-email@example.com`, then supply a new password of at least 12 characters on stdin.

For the PHP development server:

```sh
php -d upload_max_filesize=10M -d post_max_size=32M -S 127.0.0.1:8000 -t public public/router.php
```

Leave `APP_URL` empty for local automatic path detection, or set it explicitly to the public base URL without a trailing slash. Both the clean XAMPP project URL and `/public` URL are supported. Production should use `public/` as the document root, HTTPS and `APP_DEBUG=false`.

## Content management

- **Products:** create, edit, search and delete; category assignment; product image; Product Data Sheet and Safety Data Sheet PDFs; short description; rich-text features/benefits and applications; published/draft and featured states.
- **Categories:** create, edit, delete and order categories. Categories containing products cannot be deleted until those products are reassigned or removed.
- **Carousel slides:** OwlCarousel 2 slides with images, headings, descriptions, button labels/URLs, display order and published states. Autoplay is optional and respects reduced motion.
- **Company information:** ordered company sections with rich text, optional images and published states.
- **Contacts:** ordered office/department contact records with email, phone, address, opening hours and published states.
- **Brand & settings:** company name, tagline, introduction, logo, favicon, primary theme color and carousel autoplay. Updates apply across the public and admin shells.
- **Your account:** change sign-in email and password using the current password.

Public product search is available in the shared header on every public page, on the homepage and in the catalogue. It matches names, categories, short descriptions, features and applications, and combines with category filtering. Multiword queries match every word across those fields, regardless of word order. Percent and underscore characters are treated literally. Product detail pages display formatted content and PDF download links. Draft products and sections are hidden publicly. Missing product images use illustrative packaging; missing PDFs display a clear unavailable state.

Demo entries, packaging and company/contact text must be replaced with your actual content. The factory photograph is illustrative, not a photograph of this company. No certifications, product specifications or customer testimonials have been invented.

## Files, styling and security

Shared public tokens and components live in `public/assets/css/site.css` and `contrast.css`; `search.css` and `fields.css` supply the shared search layout and a single inset focus edge for form fields. `app/Views/partials/styles.php` loads each stylesheet directly with a content-based cache version; `app.css` remains a compatibility import entry point. The dashboard uses versioned `admin.css` for the compact LaLaPick shell, light/dark modes and compact/comfortable density. Small radii, visible keyboard focus, responsive navigation and horizontal table scrolling are shared conventions.

Image uploads accept JPEG, PNG and WebP, up to 5 MB; PDF documents accept verified PDF MIME/signature, up to 10 MB each. Uploads receive random filenames and are stored under `public/uploads/`. Replaced, removed and deleted attachments are cleaned up. HTML/SVG/script uploads are rejected. PHP/Apache upload limits are configured in `public/.htaccess` and `.user.ini`; hosts without these mechanisms need equivalent PHP configuration.

Mutations require authenticated admin sessions and CSRF tokens. Login attempts are throttled after five failures for an IP/email combination for 15 minutes. Rich-text HTML is reduced to an allowed set of formatting tags with all attributes removed. Destructive UI actions require confirmation. SQL uses prepared bindings. Back up the database and `public/uploads/` together.

Local vendor dependencies are jQuery 3.7.1 and OwlCarousel 2.3.4 (MIT licenses retained in their distribution headers). Factory image source: [Unsplash industrial equipment photo](https://images.unsplash.com/photo-1513828583688-c52646db42da). No CDN requests are needed by the running website.

## Verification

`tests/integration.py` tests authentication, password changes/logout, CSRF, every CRUD system, category deletion protection, search, drafts, sanitized formatting, uploads, invalid uploads, replacement/removal and branding propagation. `tests/browser.py` tests desktop/tablet/mobile overflow, navigation, carousel, search, editor formatting, delete confirmation, theme/density modes, reduced motion and JavaScript errors. Browser screenshots are written to ignored `storage/qa/`.

Run tests against a **disposable seeded local database**: they create/delete records and temporarily update branding and account passwords. Set `SK_ADMIN_PASSWORD` in the process environment and optionally `SK_TEST_URL` (default `http://127.0.0.1:8000`). Python integration dependencies: `requests`, `Pillow`; browser dependencies: `playwright` with Microsoft Edge installed. Run `python tests/integration.py` then `python tests/browser.py`. No test passwords are stored in source.

Verified at 1440×900, 1280×720 dark, 1024×768 tablet and 390×844 mobile, with both dashboard densities. The in-app browser connector was unavailable during implementation; visual verification used system Edge through Playwright.
