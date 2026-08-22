=== Init Void Shield – Zero-DB, Honeypot, Bot-Blocking ===
Contributors: brokensmile.2103
Tags: antispam, honeypot, comments, spam, no-captcha
Requires at least: 5.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zero-DB, zero-external-JS honeypot anti-spam for WordPress comments. Invisible to humans, a void to bots.

== Description ==

**Init Void Shield** protects your WordPress comment forms with a 5-layer honeypot defense that requires no database tables, no external JavaScript, and no user friction.

This plugin is part of the [Init Plugin Suite](https://en.inithtml.com/init-plugin-suite-minimalist-powerful-and-free-wordpress-plugins/) — a collection of minimalist, fast, and developer-focused tools for WordPress.

**How it works (5 layers):**

1. **Dynamic field names** — derived from post ID + site salt so bots cannot hardcode field names.
2. **CSS-clipped honeypots** — a text field and a checkbox hidden via `clip: rect()` (not `display:none`) that bots fill but humans never see.
3. **Signed time tokens** — each form carries a timestamp + HMAC hash verified server-side with `hash_equals()` to prevent timing attacks. Submissions under the minimum threshold are rejected.
4. **JavaScript verification** — a hidden token is injected after a configurable delay. Static crawlers and instant headless browsers miss it; real users don't.
5. **Block REST API Comments** — an optional setting to reject comments posted directly through the `wp/v2/comments` REST endpoint, which the classic form-based layers cannot cover since those requests never carry the honeypot fields or tokens.

**Key design goals:**

- No database clutter (zero tables, zero rows)
- No external JS/CDN calls
- No CAPTCHA, no puzzles, no user interruption
- Logged-in users are bypassed automatically (optional override in settings)
- Bots receive HTTP 200 OK so they think they succeeded and move on

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate via **Plugins → Init Void Shield**
3. Go to **Settings → Init Void Shield** to review or adjust thresholds

== Screenshots ==

1. Settings page

== Frequently Asked Questions ==

= Does this work with page builders or custom comment forms? =
The plugin hooks into `comment_form_after_fields`. If your theme uses a custom form, you may need to adjust the hook or manually call the render function.

= Can I use this alongside Akismet or other anti-spam plugins? =
Yes. Init Void Shield acts as the first line of defense. Other plugins can serve as a secondary layer.

= Will this block legitimate users? =
No. Logged-in users are bypassed by default. Guests only need to wait a few seconds between page load and submit — something every human naturally does.

= Can I force verification for logged-in users too? =
Yes. Enable **Apply to Logged-in Users** in the settings if your site has open registration or untrusted members.

= Can developers customize the behavior? =
Yes. A comprehensive filter API is available. See the documentation on GitHub.

= Does this protect comments submitted through the REST API? =
Not by default. Layers 1–4 only run on the classic comment form (`preprocess_comment`); comments posted directly to `wp/v2/comments` never carry a honeypot or JS token, so those checks don't apply. Layer 5 is available to block that endpoint entirely if you do not use a headless app or other legitimate REST client for comments.

== Changelog ==

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
