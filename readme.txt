=== Init Void Shield – Zero-DB, Honeypot, Anti-Spam ===
Contributors: brokensmile.2103
Tags: antispam, honeypot, comments, spam, no-captcha
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zero-DB, zero-external-JS honeypot anti-spam for WordPress comments, core forms, and popular form plugins. Invisible to humans, a void to bots.

== Description ==

**Init Void Shield** protects WordPress comment forms, the default login/registration/lost-password forms, and popular form plugins with a layered honeypot defense that requires no database tables, no external JavaScript, and no user friction.

This plugin is part of the [Init Plugin Suite](https://en.inithtml.com/init-plugin-suite-minimalist-powerful-and-free-wordpress-plugins/) — a collection of minimalist, fast, and developer-focused tools for WordPress.

GitHub repository: [https://github.com/brokensmile2103/init-void-shield](https://github.com/brokensmile2103/init-void-shield)

**Core honeypot engine (always on for comments):**

1. **Dynamic field names** — derived from context + site salt (plus an optional custom prefix) so bots cannot hardcode field names.
2. **CSS-clipped honeypots** — a text field and a checkbox hidden with rotating CSS techniques (never `display:none` or `visibility:hidden`, the two patterns CSS-aware bots specifically look for and skip) that bots fill but humans never see.
3. **Signed time tokens** — each form carries a timestamp + HMAC hash verified server-side with `hash_equals()` to prevent timing attacks. Submissions under the minimum threshold are rejected.
4. **JavaScript + headless-browser verification** — a hidden token is injected after a configurable delay, and the script flags common automation signals (`navigator.webdriver`, a zero-size browser window) picked up from real Selenium/Puppeteer/Playwright sessions. Static crawlers, instant bots, and unmasked headless browsers all get caught; real users don't.
5. **Block REST API Comments** *(optional)* — rejects comments posted directly through the `wp/v2/comments` REST endpoint, which the classic form-based layers cannot cover since those requests never carry the honeypot fields or tokens.

**New in 1.2 — optional, opt-in guards you can turn on individually:**

- **WordPress core forms** — the same honeypot engine can guard the default login, registration, and lost-password forms at `wp-login.php`.
- **Form plugin integrations** — Contact Form 7, WPForms, and Gravity Forms each get a one-click toggle; the guard only activates if the corresponding plugin is actually installed and active.
- **Custom field prefix** — change the honeypot field-name prefix if you suspect a spammer has targeted your site specifically.
- **CSS trap rotation** — randomizes which hiding technique and field order is used on every render, so bots can't learn one fixed pattern.
- **Lightweight statistics** — optional counters of blocked submissions by channel and reason, stored in a single non-autoloaded option (no per-submission logs, no personal data).

**Key design goals:**

- No database clutter (zero tables, zero rows; stats use a single non-autoloaded option)
- No external JS/CDN calls
- No CAPTCHA, no puzzles, no user interruption
- Logged-in users are bypassed automatically on the comment form (optional override in settings)
- Every guard beyond the core comment form is opt-in — nothing new is silently turned on when you update
- Bots receive HTTP 200 OK on the comment form so they think they succeeded and move on

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate via **Plugins → Init Void Shield**
3. Go to **Settings → Init Void Shield** to review or adjust thresholds, and to opt in to the WordPress core form guards or any form plugin integrations you use

== Screenshots ==

1. Settings page

== Frequently Asked Questions ==

= Does this work with page builders or custom comment forms? =
The plugin hooks into `comment_form_after_fields`. If your theme uses a custom form, you may need to adjust the hook or manually call the render function.

= Can I use this alongside Akismet or other anti-spam plugins? =
Yes. Init Void Shield acts as the first line of defense. Other plugins can serve as a secondary layer.

= Will this block legitimate users? =
No. Logged-in users are bypassed by default on the comment form. Guests only need to wait a few seconds between page load and submit — something every human naturally does.

= Can I force verification for logged-in users too? =
Yes. Enable **Apply to Logged-in Users** in the settings if your site has open registration or untrusted members.

= Does this protect comments submitted through the REST API? =
Not by default. The core layers only run on the classic comment form (`preprocess_comment`); comments posted directly to `wp/v2/comments` never carry a honeypot or JS token, so those checks don't apply. The optional "Block REST API Comments" setting is available to close that endpoint entirely if you do not use a headless app or other legitimate REST client for comments.

= Are the WordPress login/registration/lost-password guards enabled by default? =
No, all three are off by default since they guard authentication itself. Turn them on individually under **WordPress Core Forms** in the settings, and confirm your own login flow still works afterward. Each covers WordPress's own default form markup at `wp-login.php`, and the login guard also covers `wp_login_form()` when used on the front end (e.g. a widget or theme template). A custom login page/plugin, or Multisite's `wp-signup.php` registration flow, renders different markup and isn't covered. Each guard can also be force-disabled per request with a dedicated filter (`init_plugin_suite_void_shield_skip_login_verification`, `..._skip_register_verification`, `..._skip_lostpassword_verification`), which takes priority over the settings-page toggle.

= Are the Contact Form 7 / WPForms / Gravity Forms integrations enabled by default? =
No. Each is off by default and only takes effect if the matching plugin is active — the settings page shows a "detected / not detected" status next to each toggle.

= Can I change the honeypot field names? =
Yes. Set a **Custom Field Prefix** under Advanced Protection. Field names are still dynamically derived per context and site salt on top of that prefix.

= Does the headless-browser detection call any external service? =
No. It only reads `navigator.webdriver` and the browser window size on the visitor's own device — no external requests, no tracking.

= What does the statistics feature store? =
Only aggregate counters (a total, a breakdown by channel, a breakdown by block reason, and a last-blocked timestamp) in one non-autoloaded option. No per-submission logs, IP addresses, or personal data are recorded. It can be turned off or reset from the settings page at any time.

= Can developers customize the behavior? =
Yes. A comprehensive filter API is available. See the documentation on GitHub.

== Changelog ==

= 1.3 – August 24, 2026 =
* Fixed: the login guard now also covers `wp_login_form()` (used to place a login form anywhere on the front end, e.g. a widget or theme template). Previously only the native wp-login.php form was guarded; a front-end `wp_login_form()` submission carried no honeypot fields and was always rejected with "Invalid login attempt." once the login guard was enabled.
* Added three developer filters — `init_plugin_suite_void_shield_skip_login_verification`, `init_plugin_suite_void_shield_skip_register_verification`, and `init_plugin_suite_void_shield_skip_lostpassword_verification` — to force-disable each WordPress Core Forms guard for a given request regardless of its settings-page toggle.

= 1.2 – August 23, 2026 =
* Added optional honeypot guards for the default WordPress login, registration, and lost-password forms (each off by default; enable individually under WordPress Core Forms).
* Added optional honeypot integrations for Contact Form 7, WPForms, and Gravity Forms (each off by default; only activates if the corresponding plugin is active).
* Added a custom honeypot field-name prefix setting under Advanced Protection.
* Added CSS trap rotation: the inline hiding technique and trap field order are now randomized per render to make pattern-learning harder for CSS-aware bots.
* Added lightweight, opt-out statistics (total/by-channel/by-reason blocked-submission counters) stored in a single option with autoload explicitly disabled, plus a reset action on the settings page.
* Added client-side headless-browser detection (`navigator.webdriver`, zero-size window) layered on top of the existing JS token check; no external calls.
* Refactored the honeypot engine into a shared internal module reused by the comment form, the WP core form guards, and all three form-plugin integrations.
* Fixed a duplicate "Settings saved." admin notice on the settings page (WordPress core already prints this automatically for pages under the Settings menu; the plugin no longer prints it a second time).
* Changed: the `init_plugin_suite_void_shield_honeypot_html` filter's second parameter is now the generic guard context string (e.g. `comment_123`, `login`, `cf7_4`) instead of only a numeric comment post ID.

= 1.1 – August 13, 2026 =
* Added an optional Layer 5: "Block REST API Comments" setting to reject comments posted directly through the `wp/v2/comments` REST endpoint, which the classic 4-layer honeypot cannot cover since it never sees those requests.
* Removed the legacy pre-5.7 inline script fallback; the plugin now always uses `wp_get_inline_script_tag()`, matching the existing "Requires at least: 5.7" requirement.

= 1.0 – July 30, 2026 =
- Initial release
- 4-layer honeypot: dynamic fields, CSS-clipped traps, signed time tokens, JS verification
- Settings page: enable/disable, apply to logged-in users, min submit time, JS delay
- Full filter API for developers
- Zero database footprint
- Soft-kill with HTTP 200 to deceive bots

== License ==

This plugin is licensed under the GPLv2 or later.
