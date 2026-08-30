=== Init Void Shield – Zero-DB, Honeypot, Anti-Spam ===
Contributors: brokensmile.2103
Tags: antispam, honeypot, comments, spam, no-captcha
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5
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

**New in 1.5:**

- **Login Guard Scope** — a new "wp-login.php only" option for the Login Guard: stop guarding a front-end `wp_login_form()` usage (e.g. a custom login page, widget, or modal) entirely, keeping full protection on the native `wp-login.php` form. Useful if that front-end form lives on a page a full-page cache might serve stale. Uses the Referer header as a heuristic to tell the two forms apart — a deliberate, disclosed trade-off; the default ("Everywhere") remains the strongest option.
- **Higher Maximum Token Age ceiling** — raised from 24 hours to 30 days, for sites with long-lived full-page caching.
- **Lazy Fetch (Cache-Safe Tokens)** — new optional layer (off by default): refreshes the time token via a small same-origin `fetch()` request (plain JavaScript, no jQuery) as soon as the page truly loads, instead of relying only on the value baked in when the page was rendered — which, on a cached page, reflects when the cache was generated, not when a real visitor loaded it. Applies to every guarded form. If the request fails or JavaScript is unavailable, the original baked-in token is used as a fallback, so this can only help, never hurt.

**New in 1.4:**

- **Context-bound, self-expiring time tokens** — the signed time token is now bound to the specific form it was issued for and rejected once it goes stale, hardening the core anti-replay layer.
- **Optional real-interaction check** — under Advanced Protection, require at least one genuine mouse, keyboard, touch, or scroll event before a submission is accepted, catching bots that simply wait out the JS delay instead of interacting with the page. Off by default.
- **Three more integrations** — WooCommerce (My Account registration), bbPress (New Topic and Reply forms), and BuddyPress (registration), plus a guard for the Multisite site/user signup form (`wp-signup.php`). Each is off by default, same as the existing integrations.
- **Optional Dashboard widget** — a compact "Blocked Submissions" summary on the WordPress Dashboard, off by default, visible only to users who can manage options.

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
No, all three are off by default since they guard authentication itself. Turn them on individually under **WordPress Core Forms** in the settings, and confirm your own login flow still works afterward. Each covers WordPress's own default form markup at `wp-login.php`, and the login guard also covers `wp_login_form()` when used on the front end (e.g. a widget or theme template), unless you set **Login Guard Scope** to "wp-login.php only" — see the next question. A custom login page/plugin, or Multisite's `wp-signup.php` registration flow, renders different markup and isn't covered. Each guard can also be force-disabled per request with a dedicated filter (`init_plugin_suite_void_shield_skip_login_verification`, `..._skip_register_verification`, `..._skip_lostpassword_verification`), which takes priority over the settings-page toggle.

= What is Login Guard Scope? =
By default ("Everywhere"), the Login Guard protects both the native `wp-login.php` form and any front-end `wp_login_form()` usage (e.g. a custom login page, widget, or modal). If that front-end form lives on a page your cache plugin might serve stale, switch the scope to **"wp-login.php only"** to leave it entirely unguarded rather than risk a real visitor's login being rejected — the native `wp-login.php` form stays fully protected either way. This uses the Referer header as a heuristic to tell the two forms apart, which a determined bot could spoof, so it's a deliberate trade-off: only switch away from "Everywhere" if you've hit this specific caching situation.

= Are the Contact Form 7 / WPForms / Gravity Forms integrations enabled by default? =
No. Each is off by default and only takes effect if the matching plugin is active — the settings page shows a "detected / not detected" status next to each toggle.

= Are the WooCommerce / bbPress / BuddyPress integrations enabled by default? =
No, same as the other integrations: each is off by default and only takes effect if the matching plugin is active. WooCommerce guards the "My Account" registration form; bbPress guards the New Topic and Reply forms; BuddyPress guards the registration (signup) form. The Multisite signup guard (`wp-signup.php`) only has an effect on a Multisite install and is also off by default.

= Why aren't Ninja Forms or Forminator supported? =
Both build their forms client-side and collect submitted data through their own JavaScript field model rather than serializing the whole `<form>` element, so a honeypot field simply appended to the page would not reliably be sent to the server — it would look like protection without actually doing anything. Both also already ship their own built-in honeypot field. Support may be reconsidered if a reliable hook becomes available.

= What is the Maximum Token Age setting? =
Each guard issues a signed token good for a limited time window: too fast (below Minimum Submit Time) and too old (above Maximum Token Age) are both rejected. The ceiling exists so a token cannot be captured once and replayed indefinitely; the default of 1 hour is generous enough for a visitor who takes their time filling out a form. Increase it (up to 30 days) if legitimate visitors on your site routinely take longer than that, or if your site uses full-page caching — see the next question.

= My site uses aggressive full-page caching and legitimate comments are being rejected as "expired" — what do I do? =
On a cached page, the time token baked into the HTML reflects the moment the page was cached, not the moment a real visitor loaded it. If your cache lifetime is long, that gap can exceed the Maximum Token Age. Two options, either works on its own: raise Maximum Token Age to comfortably cover your cache lifetime, or enable **Lazy Fetch** under Advanced Protection, which refreshes the token client-side right after the page truly loads, so timing is always measured from the real visit regardless of cache age.

= What does Lazy Fetch do, and is it safe? =
When enabled, a small same-origin JavaScript request (no jQuery, no external service) fetches a fresh, correctly signed time token right after the page loads, and overwrites the one baked in at render time. It's stateless — the endpoint only recomputes the same signed hash the page would already have shown, and is only registered at all while Lazy Fetch is enabled. If the request fails, or JavaScript is unavailable, the original baked-in token is used as-is, exactly like before Lazy Fetch existed — so enabling it can only help, never introduce a new failure mode. Off by default; applies to every guarded form, not just comments.

= I updated from an older version and my Minimum Submit Time / JS Token Delay no longer behaves the way I remembered — why? =
Versions before 1.4 had a bug where these two settings were saved correctly but never actually read back during verification, so the plugin always silently enforced the defaults (3 seconds / 1000ms) no matter what was configured. 1.4 fixes this, so a custom value you set earlier may now be enforced for the first time. This is a bug fix, not a new restriction — just double-check both values on the settings page after updating.

= What does "Require Real User Interaction" do? =
When enabled, a submission is only accepted if the browser recorded at least one real mouse, keyboard, touch, or scroll event before the JS token fires. It targets bots that wait out the JavaScript Token Delay instead of a real page visit. It's off by default and works best paired with a JS delay of at least 1-2 seconds.

= Can I change the honeypot field names? =
Yes. Set a **Custom Field Prefix** under Advanced Protection. Field names are still dynamically derived per context and site salt on top of that prefix.

= Does the headless-browser detection call any external service? =
No. It only reads `navigator.webdriver` and the browser window size on the visitor's own device — no external requests, no tracking.

= What does the statistics feature store? =
Only aggregate counters (a total, a breakdown by channel, a breakdown by block reason, and a last-blocked timestamp) in one non-autoloaded option. No per-submission logs, IP addresses, or personal data are recorded. It can be turned off or reset from the settings page at any time.

= Is the Dashboard widget on by default? =
No. Enable **Show Dashboard Widget** under Statistics. It's only visible to users who can manage options, and only shows the same aggregate counters as the settings page (no per-submission data).

= Can developers customize the behavior? =
Yes. See the **Filters** section below for the full list, or the documentation on GitHub for more detail.

== Filters ==

A short reference of the developer filters shipped with the plugin (all are standard WordPress filters, added with `add_filter()`):

* `init_plugin_suite_void_shield_skip_verification` — skip comment-form verification for a request.
* `init_plugin_suite_void_shield_skip_login_verification` / `_register_verification` / `_lostpassword_verification` / `_multisite_signup_verification` — force-disable an individual WordPress Core Forms guard, overriding its settings-page toggle.
* `init_plugin_suite_void_shield_login_scope_exempt` — override the Referer-based heuristic used by the "wp-login.php only" Login Guard Scope.
* `init_plugin_suite_void_shield_honeypot_html` — filter the rendered honeypot HTML block; receives the context string as a second argument.
* `init_plugin_suite_void_shield_kill_response_message` / `_title` / `_code` — customize the soft-kill response shown to bots on the comment form.
* `init_plugin_suite_void_shield_min_time` / `_max_time` / `_js_delay` — override the Minimum Submit Time, Maximum Token Age, and JS Token Delay thresholds.
* `init_plugin_suite_void_shield_hidden_style_variants` — customize the pool of CSS techniques used to hide honeypot fields.
* `init_plugin_suite_void_shield_{context}_blocked_message` — customize the rejection message for a given guard (e.g. `..._login_blocked_message`, `..._woocommerce_blocked_message`, `..._bbpress_blocked_message`).

== Changelog ==

= 1.5 – August 30, 2026 =
* Added **Login Guard Scope**: a new "wp-login.php only" option for the Login Guard, which stops guarding a front-end `wp_login_form()` usage (e.g. a custom login page, widget, or modal) entirely while keeping full protection on the native `wp-login.php` form. Intended for sites where that front-end form lives on a page a full-page cache might serve stale; uses the Referer header as a heuristic to tell the two forms apart (a disclosed trade-off, not a cryptographic guarantee). The default ("Everywhere") is unchanged and remains the strongest option.
* Raised the **Maximum Token Age** ceiling from 24 hours to 30 days, so sites with long-lived full-page caching can set a value that comfortably covers their cache lifetime.
* Added **Lazy Fetch (Cache-Safe Tokens)** under Advanced Protection (off by default): refreshes the time token via a small same-origin `fetch()` request (plain JavaScript, no jQuery, no external service) as soon as the page truly loads, instead of relying only on the value baked in at render time — which, on a cached page, reflects when the cache was generated rather than when a real visitor loaded it. Applies to every guarded form. Falls back to the original baked-in token if the request fails or JavaScript is unavailable, so enabling it can only help, never introduce a new failure mode. Its REST route is registered under the `initvoshi/v1` namespace, matching the naming convention used across the Init Plugin Suite.

= 1.4 – August 30, 2026 =
* Security: the signed time token is now bound to the specific guard context it was issued for (previously it was only a function of the timestamp, so a valid token captured from one form — e.g. a public comment form — could in theory be replayed against a different, more sensitive context, such as the login guard). Field names already differed per context, but the token itself did not.
* Security: added a **Maximum Token Age** setting (default 1 hour) so a token cannot be captured once and cached for an unlimited-time replay. Submissions carrying a token older than this are now rejected with a new `token_expired` block reason.
* Fixed: the **Minimum Submit Time** and **JavaScript Token Delay** settings on the settings page were not actually being read during verification/rendering — the code always used the filter defaults (3 seconds / 1000ms) regardless of what was saved. Both settings now take effect as expected; the corresponding developer filters still apply on top. **Note for existing users:** if you had previously changed either value away from the default, it silently had no effect before — after updating it will now be genuinely enforced, so it's worth revisiting both values on the settings page to confirm they're still what you want.
* Added an optional **Require Real User Interaction** check under Advanced Protection (off by default): also requires at least one real mouse, keyboard, touch, or scroll event before a submission is accepted, to catch bots that simply wait out the JS delay without interacting with the page.
* Added optional honeypot integrations for **WooCommerce** (My Account registration form), **bbPress** (New Topic and Reply forms), and **BuddyPress** (registration form) — each off by default, only activates if the matching plugin is active.
* Added an optional **Multisite signup guard** for `wp-signup.php` (off by default, only has an effect on a Multisite install).
* Added an optional **Dashboard widget** (off by default) showing a compact "Blocked Submissions" summary with the top blocked channels and reasons.
* Updated translation template (`.pot`) and the Vietnamese translation for all new strings.

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
