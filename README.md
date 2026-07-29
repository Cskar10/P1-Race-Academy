# P1 Race Academy — Custom Code (`go-kart-dev`)

Version-controlled **custom code** for the P1 Race Academy WordPress site
(`p1raceacademy.com`). This is **not** a full-site backup — see "What's deliberately
not here" below.

The layout mirrors `wp-content/` so DeployHQ can send the custom code straight
to Rocket.net staging over SFTP.

- **Source of truth:** private GitHub repo `Cskar10/GoCart`
- **Deployment target:** Rocket.net staging first; publish to production only after verification
- **Historical/local dev:** LocalWP site `go-kart-dev` → `~/Local Sites/go-kart-dev/app/public`
- **Production host:** Rocket.net (locked 2026-05-14), Pressable fallback
- **Repo restructured to code-only:** 2026-06-09

## What's in here

### `wp-content/mu-plugins/` — custom must-use plugins
| Plugin | Version | Purpose |
|---|---|---|
| `p1-simple-site.php` | 0.2.0 | `Simple` branch guard: keeps navigation intact, makes checkout/products/course modules unavailable, turns `/online-coaching/` into a Coming soon page, and gates Support behind account login/registration. |
| `p1-sensei-woo-bridge.php` | 0.4.0 | Maps WooCommerce products → Sensei course access. Verifies paid state, revokes on non-paid states including on-hold, reconciles mapping changes, and runs a daily orphan-grant audit. |
| `p1-checkout-flow.php` | 0.2.0 | Clean buy-now checkout (`/checkout/?p1_buy_online_coaching=1`), forces qty 1, clears stale notices. |
| `p1-purchase-welcome-flow.php` | 0.2.0 | Post-purchase welcome / FluentCRM funnel trigger. |
| `p1-video-submission-flow.php` | 0.6.0 | Private coaching-footage uploads stored outside web root (fails closed). Set `p1_video_submission_private_dir` on the host. |
| `p1-account-nav.php` | 0.4.1 | Main nav: Home · Course · Login/My Account · Dashboard · Shop · Support, with active-page highlight. My Account Downloads is relabelled as Shop and points to the shop page. |
| `p1-404-page.php` | 0.2.0 | Branded "404 · Off track" page, real HTTP 404, recovery links. |
| `p1-accessibility-polish.php` | 0.1.0 | AA contrast, focus indicators, touch targets, ARIA fixes. |
| `p1-email-readiness.php` | 0.3.0 | `wp p1-email-readiness` smoke command for transactional email and admin-sale-email tuning. |
| `p1-email-tuning.php` | 0.1.0 | Keeps WooCommerce New order email; suppresses the duplicate Sensei Course Enrolled teacher email. |
| `p1-security-hardening.php` | 0.1.0 | Local-safe browser headers, public user-enumeration reduction, XML-RPC blocking, and generic login errors. |
| `p1-hsts.php` | unversioned | Sends the production-only HSTS response header. |
| `p1-motion.php` | 0.4.0 | Front-page motion layer plus its tracked CSS, JavaScript, and kart assets. |
| `p1-red-brand-preview.php` | 0.1.0 | Staging preview of the red/white/black brand treatment. |
| `p1-h5p-popup-controls.php` | 0.2.3 | Paused coaching popups, Continue controls, per-marker rewind, overlap-safe auto-open, and immersive H5P fullscreen behavior. |
| `p1-h5p-readiness.php` | 0.4.6 | H5P interactive-layer readiness checks, including the `00:08` and `00:14` Lesson 1.1 coaching checkpoints. |

> `local-by-flywheel-live-link-helper.php` is intentionally excluded — it's LocalWP's, not ours.

Course-access operations:

- `wp p1-course-access audit` — read-only orphan-grant audit.
- `wp p1-course-access audit --fix` — revoke detected orphan grants.
- `wp p1-course-access reconcile <course-id>` — reconcile existing paid buyers after a mapping change.
- `wp p1-course-access status` — show the daily audit schedule and last result.
- `wp eval-file tests/p1-sensei-woo-bridge-regression.php` — run the disposable LocalWP regression suite.

### `Simple` branch

The `Simple` branch deliberately keeps the existing navigation but disables
the public payment and learning flows:

- WooCommerce products cannot be purchased, payment gateways are removed, and
  cart, checkout, shop, product, and buy-now requests lead to the course page.
- Sensei course, lesson, quiz, dashboard, H5P, and reflection interfaces are
  unavailable on the public site.
- `/online-coaching/` keeps its menu label but renders with the heading
  **Coming soon** and no sales or course-module content.
- `/contact/` keeps the full `main`-branch Support experience for account
  holders. Guests are sent to the existing My Account login/registration
  screen and returned to Support after authentication; direct guest video
  submissions remain disabled.

The original integrations and database records are left intact so returning to
`main` restores the full site without a content migration. Run the focused
standalone check with `php tests/p1-simple-site-regression.php`.

### `wp-content/plugins/go-kart-blocks/` — custom Gutenberg blocks
Reflection Prompt block (`P1 Race Academy Blocks`).
`build/` **is committed** so the plugin runs on the host without a build step.
To rebuild from source: `npm install && npm run build` (regenerates `build/` from `src/`).

### `db-exports/additional-css.css`
The site's **Additional CSS** (Appearance → Customize → Additional CSS), which lives in
the **database**, not a file — exported here so the P1 colour system / styling isn't lost.
Re-apply on the host with `wp_update_custom_css_post( $css )` or by pasting into the Customizer.

### `db-exports/h5p-content-3.json`
A small, reviewable snapshot of H5P Interactive Video content `#3`, **Lesson 1.1 coaching
overlays**. It records the paused coaching checkpoints currently live at:

- `00:08` — **Mid-corner oversteer**
- `00:14` — **Rear steps out again**

This snapshot deliberately excludes the packaged WebM/MP4 media and third-party H5P libraries. The
complete `.h5p` package and videos remain under `wp-content/uploads/h5p/` in LocalWP and
ride the managed migration with the rest of the uploads.

## What's deliberately NOT here (and why)
- **WordPress core, `wp-config.php`, `.htaccess`** — host-managed; secrets don't belong in git.
- **The database** (pages, Sensei course, Woo products, FluentCRM funnels, all plugin settings) —
  restored via Rocket.net's free managed migration from a LocalWP export.
- **`wp-content/uploads/` (media)** — rides the migration.
- **Third-party plugins/themes** (WooCommerce, Sensei, Kadence, H5P, FluentCRM/SMTP) — reinstalled on the host.
- **`node_modules/`** — reinstall with `npm install`.

## Deploy model
- **Initial go-live:** completed through Rocket.net's managed migration.
- **Ongoing code changes:** edit → commit → push `main` → DeployHQ → Rocket.net staging.
- **Promotion:** verify staging, then publish to production separately in Rocket.net.
- **Deployment scope:** deploy only `wp-content/`; `db-exports/`, `tests/`, repository metadata, and documentation are not server payloads.

## Key IDs (reference)
Course **#68** · Products: Driver **#129**, Parent **#130**, Full Access **#82** ·
Pages: Terms **#120** `/terms/`, Privacy **#3** `/privacy-policy/`, Footage Privacy **#122** `/footage-privacy/`, Refund **#47** (draft) ·
FluentCRM funnel **#1** / campaign **#1** / sequence **#1**.

## ⚠️ Security note
The **pre-2026-06-09 history** of this (private) repo still contains the old full-site
snapshot, including `wp-config.php` and a database dump (`local.sql`) with Stripe/Postmark
settings. The repo is private, and most values are local-dev only. Before launch, consider
rotating the **Postmark token** and **Stripe keys**, and/or scrubbing history
(`git filter-repo` / BFG) if a spotless history is wanted.
