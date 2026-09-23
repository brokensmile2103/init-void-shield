# Init Void Shield – Zero-DB, Honeypot, Anti-Spam

> Honeypot anti-spam for WordPress comments, core forms, and popular form plugins. Invisible to humans, a void to bots.

**No CAPTCHA. No external JS. No database clutter. No user friction.**

[![Version](https://img.shields.io/badge/stable-v1.11-blue.svg)](https://wordpress.org/plugins/init-void-shield/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
![Made with ❤️ in HCMC](https://img.shields.io/badge/Made%20with-%E2%9D%A4%EF%B8%8F%20in%20HCMC-blue)

## Overview

**Init Void Shield** protects your WordPress comment forms, the default login/registration/lost-password forms, Multisite signup, WooCommerce/bbPress/BuddyPress, and popular form plugins with a layered honeypot defense that requires no database tables, no external JavaScript, and no user friction — and stays accurate even on aggressively cached sites.

- Zero database footprint — no tables, no rows; stats use a single non-autoloaded option
- Zero external JS / CDN calls — everything is self-contained
- No CAPTCHA, no puzzles, no "click all buses"
- Logged-in users bypassed automatically on the comment form (optional override)
- Every guard beyond the core comment form is opt-in — no new form is silently guarded when you update
- A genuine visitor who submits before the guard is ready (e.g. right after autofill) is briefly held and submitted automatically, never rejected *(new in 1.11)*
- Bots receive HTTP 200 OK on the comment form — they think they succeeded and move on
- Full filter API for developers who want full control

Perfect for blogs, communities, news sites, online stores, or any WordPress site that wants clean comments, logins, and forms without annoying legitimate users.

## Features

- **Core honeypot engine (always on for comments)**
  1. Dynamic field names — derived from context + site salt (plus an optional custom prefix) so bots cannot hardcode field names
  2. CSS-clipped honeypots — a text field and a checkbox hidden with rotating CSS techniques (never `display:none` or `visibility:hidden`, the two patterns CSS-aware bots specifically look for and skip) that bots fill but humans never see
  3. Signed, context-bound, self-expiring time tokens — each form carries a timestamp + HMAC hash verified server-side with `hash_equals()` to prevent timing attacks. The hash is bound to the specific form it was issued for and rejected once it goes stale, so a token cannot be captured on one form and replayed against another, or cached and replayed indefinitely
  4. JavaScript + headless-browser verification — a hidden token is injected after a configurable delay plus a small random jitter, so the exact wait can't be read from the page source and timed around, and the script flags common automation signals (`navigator.webdriver`, a zero-size browser window, `HeadlessChrome`/`PhantomJS` User-Agents, Selenium/ChromeDriver traces *(new in 1.11)*) from real Selenium/Puppeteer/Playwright sessions. **The token itself is a signed proof unique to the exact form it was issued for**, never a fixed value, and it's embedded in the page only in sealed form — a bot that scrapes the static HTML and replays hidden field values can no longer copy it *(new in 1.11)*. Static crawlers, instant bots, and unmasked headless browsers all get caught; real users don't
  5. Submit Hold *(new in 1.11)* — on natively-submitted forms (WordPress core forms, comments, WooCommerce account forms, bbPress, BuddyPress), a visitor who clicks submit before the guard is ready — typically right after the browser autofills a saved password — is held for a moment and submitted automatically once it is, instead of being rejected. Nothing is relaxed server-side; every gate still requires exactly what it required before
  6. Non-Browser User-Agent detection *(on by default, extended in 1.11)* — rejects submissions whose User-Agent identifies a scripted HTTP client (curl, Python requests, Go, Scrapy, HeadlessChrome, HTTPX, aiohttp, axios, undici, HTTPie, Colly, and similar) rather than a real browser, catching a bot that skips JavaScript entirely and simply replays the static form fields
  7. Block Cross-Site Submissions *(new in 1.11, on by default)* — rejects a submission the browser itself reports as sent from another website via the `Sec-Fetch-Site` request header, which page scripts cannot forge and privacy extensions don't strip. A request without the header is never rejected by this check
  8. Block REST API Comments *(optional)* — rejects comments posted directly through the `wp/v2/comments` REST endpoint, which the classic form-based layers cannot cover
- **Comment content filters** *(new in 1.11, each opt-in and off by default)* — Maximum Links per Comment, Block BBCode Links (`[url]…[/url]` / `[link]…[/link]`), and Block URLs in Author Name. Unlike the behavioral layers above, these read what was actually written, so they also catch spam posted by a real person or a real browser, and they apply even to comments that bypass the honeypot checks (e.g. wpDiscuz)
- **WordPress core form guards** *(off by default)* — the same honeypot engine can protect the default login, registration, lost-password, and Multisite signup forms, enabled individually
- **Form plugin integrations** *(off by default)* — one-click honeypot guard for Contact Form 7, WPForms, and Gravity Forms; each only activates if the corresponding plugin is detected active
- **Community & e-commerce integrations** *(off by default)* — one-click honeypot guard for WooCommerce (My Account registration, and, new in 1.11, the WooCommerce Login and Lost Password forms), bbPress (New Topic and Reply forms), and BuddyPress (registration form); each only activates if the corresponding plugin is detected active
- **Require Real User Interaction** *(off by default)* — also requires at least one real mouse, keyboard, touch, or scroll event before a submission is accepted, catching bots that simply wait out the JS delay instead of interacting with the page. An event only counts once a short minimum delay has passed since the script started, so a bot can't satisfy this with one synthetic event fired the instant the page loads
- **Require Same-Site Referer** *(comments only, off by default)* — rejects a comment submission whose Referer header is missing or points to a different site, catching a scripted bot that posts directly to the comment endpoint without ever loading the page. Disclosed trade-off, same spirit as Login Guard Scope: some privacy-focused browsers strip Referer even on genuine same-site submissions, so this is meant to be used alongside the other layers, not instead of them
- **Lazy Fetch (Cache-Safe Tokens)** *(off by default)* — refreshes the time token and its signed JS proof via a small same-origin `fetch()` request (plain JavaScript, no jQuery) as soon as the page truly loads, instead of relying only on the value baked in at render time. On a heavily full-page-cached site that value reflects when the page was *cached*, not when a real visitor loaded it; this fixes that gap and applies to every guarded form. Falls back to the original baked-in token if the request fails, so it can only help
- **Login Guard Scope** — scope the Login Guard to "wp-login.php only" to leave a front-end `wp_login_form()` usage (widget, modal, custom login page) completely unguarded, e.g. if that page might be served by a full-page cache; the native `wp-login.php` form stays fully protected either way
- **Dashboard widget** *(off by default)* — a compact "Blocked Submissions" summary on the WordPress Dashboard, showing the top blocked channels and reasons
- **Custom field prefix** — change the honeypot field-name prefix if you suspect a spammer has targeted your site specifically
- **CSS trap rotation** — randomizes the hiding technique and trap field order on every render, so bots can't learn one fixed pattern
- **Lightweight statistics** *(opt-out)* — blocked-submission counters by channel and reason, stored in a single non-autoloaded option; no per-submission logs, no personal data
- **Soft-kill responses** — spam bots hitting the comment form get HTTP 200 OK, deceiving them into thinking the comment was posted successfully
- **Logged-in user bypass** — authenticated users skip comment-form verification by default; enable **Apply to Logged-in Users** if your site has open registration or untrusted members
- **Configurable thresholds** — adjust minimum submit time, maximum token age (up to 30 days), and JS injection delay to match your audience
- **Full filter API** — customize every layer via WordPress filters
- **Lightweight codebase** — backend-only, no frontend assets, and the inline verification script ships with no comments in its output, so guarded pages stay lean and the detection logic isn't spelled out in page source
- **Safe-by-default** — doesn't override core behavior unless a check fails, and every new guard is opt-in

## How It Works

1. A visitor loads a page with a guarded form → the plugin injects dynamic honeypot fields, a signed time token bound to that specific form, and a delayed JS + headless-browser verification token — itself a signed proof unique to that exact time token and form, embedded only in sealed form
2. A human reads, scrolls, then fills the form — naturally spending more time than the minimum threshold and behaving like a real browser. If they submit before the guard is ready (e.g. right after autofill), the submission is briefly held and sent automatically once it is, rather than being rejected
3. On submit, the server validates:
   - The browser itself doesn't report the submission as coming from another site (`Sec-Fetch-Site: cross-site`)
   - User-Agent doesn't identify a known scripted HTTP client
   - Honeypot fields are empty
   - The JS proof is present and matches the one issued for this exact form and time token, and doesn't report a headless-browser or no-interaction signal
   - Time token is valid for this exact form, was submitted neither too fast nor too long after page load
   - *(Optional)* Referer header points to this site, if Require Same-Site Referer is enabled
   - *(Optional, comments only)* The comment content passes the enabled content filters (link count, BBCode links, author-name URL)
4. If any check fails → the submission is rejected (soft HTTP 200 kill on the comment form; a normal validation error on core/form-plugin forms)
5. *(Optional)* If **Block REST API Comments** is enabled, direct POSTs to `wp/v2/comments` are rejected entirely — closing the gap the classic form layers can't see

No friction for humans. No clues for bots.

## Settings

Navigate to:

```
Settings → Init Void Shield
```

**Comments**
- **Enable / Disable** — master toggle for the comment-form guard
- **Apply to Logged-in Users** — force verification even for authenticated users
- **Block REST API Comments** — reject comments posted directly to the REST API endpoint `wp/v2/comments`
- **Require Same-Site Referer** *(off by default)* — reject a comment submission whose Referer header is missing or points to a different site; disclosed trade-off, see Features above
- **Maximum Links per Comment** *(new in 1.11, off by default — 0)* — reject a comment containing more links than this
- **Block BBCode Links** *(new in 1.11, off by default)* — reject a comment containing a complete `[url]…[/url]` or `[link]…[/link]` pair; WordPress never renders BBCode anyway
- **Block URLs in Author Name** *(new in 1.11, off by default)* — reject a comment whose Name field contains a web address

**Timing & Token Engine** — shared by every guarded form, not just comments
- **Minimum Submit Time** — seconds a content-style form (comments, topics, replies, contact forms) must take between page load and submit (default: 3)
- **Account Forms Minimum Submit Time** — a separate, shorter floor for Login, Registration, Lost Password, Multisite Signup, WooCommerce account forms, and BuddyPress registration, since autofill lets a genuine visitor submit faster (default: 1)
- **JavaScript Token Delay** — milliseconds before the JS token is injected into the form (default: 1000). A small random jitter is always added on top, so the exact wait can't be read from the page source and timed around. On natively-submitted forms, a submission that arrives early is held rather than rejected — see Submit Hold
- **Maximum Token Age** — seconds after which a token is considered stale and rejected, so it can't be captured once and replayed indefinitely (default: 3600, up to 2,592,000 / 30 days)

**WordPress Core Forms** *(off by default)*
- **Guard Login Form**, **Guard Registration Form**, **Guard Lost Password Form** — protect the default WP forms at `wp-login.php`. Each covers only WordPress's own default markup, plus `wp_login_form()` on the front end for the login guard; a custom login page/plugin isn't covered
- **Login Guard Scope** — "Everywhere" (default) covers both `wp-login.php` and any front-end `wp_login_form()` usage; "wp-login.php only" leaves the front-end usage entirely unguarded (e.g. if it lives on a page a full-page cache might serve stale) using the Referer header as a heuristic to tell the two apart
- **Guard Multisite Signup Form** — protect the site/user signup form at `wp-signup.php`; only has an effect on a Multisite install

**Form Plugin Integrations** *(off by default)*
- **Contact Form 7**, **WPForms**, **Gravity Forms** — one toggle per plugin; only takes effect if that plugin is active (a status badge on the settings page shows detection)

**Community & E-commerce Integrations** *(off by default)*
- **WooCommerce Registration**, **WooCommerce Login**, **WooCommerce Lost Password** *(login/lost-password new in 1.11)* — the two new guards cover the WooCommerce login form (My Account and the checkout login prompt) and its own lost-password form. WooCommerce Lost Password is also always in effect while Guard Lost Password Form is enabled, since WooCommerce's reset handler shares the same core hook but its form never carried the honeypot fields
- **bbPress Topics & Replies**, **BuddyPress Registration** — one toggle per plugin; only takes effect if that plugin is active (a status badge on the settings page shows detection)

**Advanced Protection**
- **Custom Field Prefix** — prefix used to build honeypot field names (lowercase letters, numbers, underscore only)
- **CSS Trap Rotation** — randomize the CSS technique and field order used to hide traps on every render
- **Headless Browser Detection** *(extended in 1.11)* — flag `navigator.webdriver`, zero-size browser windows, `HeadlessChrome`/`PhantomJS` User-Agents, and Selenium/ChromeDriver automation markers via the JS token script; no external calls
- **Block Non-Browser User Agents** *(on by default, extended in 1.11)* — reject submissions whose User-Agent identifies a known scripted HTTP client; the signature list is filterable
- **Block Cross-Site Submissions** *(new in 1.11, on by default)* — reject a submission the browser itself reports as sent from a different website (`Sec-Fetch-Site: cross-site`); only disable if a form on an unrelated domain legitimately posts to your site
- **Require Real User Interaction** *(off by default)* — also require at least one real mouse, keyboard, touch, or scroll event before a submission is accepted, only counted after a short minimum delay since script start
- **Lazy Fetch (Cache-Safe Tokens)** *(off by default)* — refresh the time token and its signed JS proof client-side right after the page loads instead of relying only on the value baked in at render time; recommended for sites with aggressive full-page caching

**Statistics** *(on by default, opt-out)*
- **Track Blocked Submissions** — lightweight, non-autoloaded counters (total, by channel, by reason), with a reset action
- **Show Dashboard Widget** *(off by default)* — a compact "Blocked Submissions" summary on the WordPress Dashboard, visible to users who can manage options

## Filters for Developers

Init Void Shield ships a full filter API so you can customize its behavior without touching core files.

**Skip verification for a specific request:**
```php
add_filter( 'init_plugin_suite_void_shield_skip_verification', '__return_true' );
```

**Force-disable an individual WordPress core form guard, regardless of the settings-page toggle** (each takes priority over the UI setting):
```php
add_filter( 'init_plugin_suite_void_shield_skip_login_verification', '__return_true' );
add_filter( 'init_plugin_suite_void_shield_skip_register_verification', '__return_true' );
add_filter( 'init_plugin_suite_void_shield_skip_lostpassword_verification', '__return_true' );
add_filter( 'init_plugin_suite_void_shield_skip_multisite_signup_verification', '__return_true' );
```

**Skip the opt-in comment content filters for a specific request** *(new in 1.11)*:
```php
add_filter( 'init_plugin_suite_void_shield_skip_content_filters', '__return_true' );
```

**Add your own comment content rules** *(new in 1.11)* — return a non-empty reason key to block the comment (recorded in the stats under that key), or an empty string to let it through:
```php
add_filter( 'init_plugin_suite_void_shield_comment_content_violation', function ( $reason, $commentdata ) {
    return $reason;
}, 10, 2 );
```

**Override the Login Guard Scope's Referer-based exemption heuristic** (`$referer` is the raw header value that was checked):
```php
add_filter( 'init_plugin_suite_void_shield_login_scope_exempt', function ( $is_exempt, $referer ) {
    return $is_exempt;
}, 10, 2 );
```

**Force-exempt a request from the Require Same-Site Referer check**, regardless of its settings-page toggle:
```php
add_filter( 'init_plugin_suite_void_shield_referer_exempt', '__return_true' );
```

**Force-exempt a request from the Block Cross-Site Submissions check** *(new in 1.11)* — useful when a form on an unrelated domain legitimately posts to your site (e.g. a Multisite network with subsites on unrelated domains sharing one login form); `$context` is passed as a second argument:
```php
add_filter( 'init_plugin_suite_void_shield_cross_site_exempt', function ( $exempt, $context ) {
    return $exempt;
}, 10, 2 );
```

**Enable or disable Submit Hold for a given guard context** *(new in 1.11)* — useful for a custom natively-submitted form added via `init_plugin_suite_void_shield_build_guard_markup()`:
```php
add_filter( 'init_plugin_suite_void_shield_submit_hold_enabled', function ( $enabled, $context ) {
    return $enabled;
}, 10, 2 );
```

**Customize the maximum time Submit Hold waits for a pending Lazy Fetch request** *(new in 1.11, milliseconds, default 4000)*:
```php
add_filter( 'init_plugin_suite_void_shield_submit_hold_fetch_wait', function ( $milliseconds, $context ) {
    return $milliseconds;
}, 10, 2 );
```

**Customize the honeypot HTML block** — `$context` is a context string (`comment_123`, `login`, `woocommerce_login`, `cf7_4`, `woocommerce_register`, `bbpress_topic`, `buddypress_register`, `multisite_signup`…):
```php
add_filter( 'init_plugin_suite_void_shield_honeypot_html', function ( $html, $context ) {
    return $html;
}, 10, 2 );
```

**Customize the message/title/response code shown when a bot is soft-killed on the comment form:**
```php
add_filter( 'init_plugin_suite_void_shield_kill_response_message', function ( $msg ) {
    return 'Spam detected.';
} );
add_filter( 'init_plugin_suite_void_shield_kill_response_title', function ( $title ) {
    return 'Blocked';
} );
add_filter( 'init_plugin_suite_void_shield_kill_response_code', function ( $code ) {
    return 200; // Default: 200, so bots believe the spam succeeded.
} );
```

**Customize the Minimum Submit Time, Maximum Token Age, or JS Token Delay** (each still reads the settings-page value first; the filter applies on top; `min_time` and `max_time` also receive the guard context as a second argument):
```php
add_filter( 'init_plugin_suite_void_shield_min_time', function ( $seconds, $context ) {
    return 5;
}, 10, 2 );
add_filter( 'init_plugin_suite_void_shield_max_time', function ( $seconds, $context ) {
    return 7200;
}, 10, 2 );
add_filter( 'init_plugin_suite_void_shield_js_delay', function ( $milliseconds ) {
    return 1500;
} );
```

**Customize the JS Token Delay jitter or the minimum interaction delay**:
```php
add_filter( 'init_plugin_suite_void_shield_js_delay_jitter_max', function ( $milliseconds ) {
    return 600; // Default: 400.
} );
add_filter( 'init_plugin_suite_void_shield_min_interaction_delay', function ( $milliseconds ) {
    return 250; // Default: 150. Only relevant if Require Real User Interaction is on.
} );
```

**Customize the list of User-Agent substrings flagged as non-browser clients:**
```php
add_filter( 'init_plugin_suite_void_shield_blocked_user_agent_signatures', function ( $signatures ) {
    $signatures[] = 'my-internal-test-client';
    return $signatures;
} );
```

**Customize the pool of CSS techniques used to hide the honeypot fields:**
```php
add_filter( 'init_plugin_suite_void_shield_hidden_style_variants', function ( $variants ) {
    $variants[] = 'position:absolute;height:0;overflow:hidden;';
    return $variants;
} );
```

**Customize the block reason shown alongside a rejected submission** on integrations that surface one (e.g. WooCommerce registration/login/lost-password, bbPress, BuddyPress, Multisite signup) — each has its own `..._blocked_message` filter, for example:
```php
add_filter( 'init_plugin_suite_void_shield_login_blocked_message', function ( $message ) {
    return 'Login blocked.';
} );
```

## Upgrading to 1.11

1.11 replaces the fixed JS token value with a signed, per-form proof. **Purge your page cache (and any CDN cache) once after updating** — a page still served from a cache built before the update contains the old script, and its submissions will fail with a new "Invalid JS proof" reason until the cache is refreshed.

## Installation

1. Upload plugin folder to `/wp-content/plugins/`
2. Activate under **Plugins → Init Void Shield**
3. Go to **Settings → Init Void Shield** to review or adjust thresholds, and to opt in to the WordPress core form guards or any form plugin/e-commerce/community integrations you use
4. Done — your comment forms are protected out of the box; everything else is one toggle away

## License

GPLv2 or later — open source, minimal, developer-first.

## Part of Init Plugin Suite

Init Void Shield is part of the [Init Plugin Suite](https://en.inithtml.com/init-plugin-suite-minimalist-powerful-and-free-wordpress-plugins/) — a collection of blazing-fast, no-bloat plugins made for WordPress developers who care about quality and speed.
