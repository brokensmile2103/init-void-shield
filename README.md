# Init Void Shield – Zero-DB, Honeypot, Bot-Blocking

> 5-layer honeypot anti-spam for WordPress comments. Invisible to humans, a void to bots.

**No CAPTCHA. No external JS. No database clutter. No user friction.**

[![Version](https://img.shields.io/badge/stable-v1.1-blue.svg)](https://wordpress.org/plugins/init-void-shield/)
[![License](https://img.shields.io/badge/license-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
![Made with ❤️ in HCMC](https://img.shields.io/badge/Made%20with-%E2%9D%A4%EF%B8%8F%20in%20HCMC-blue)

## Overview

**Init Void Shield** protects your WordPress comment forms with a 5-layer honeypot defense that requires no database tables, no external JavaScript, and no user friction.

- Zero database footprint — no tables, no rows, no clutter  
- Zero external JS / CDN calls — everything is self-contained  
- No CAPTCHA, no puzzles, no "click all buses"  
- Logged-in users bypassed automatically (optional override)  
- Bots receive HTTP 200 OK — they think they succeeded and move on  
- Full filter API for developers who want full control  

Perfect for blogs, communities, news sites, or any WordPress site that wants clean comment sections without annoying legitimate users.

## Features

- **5-layer honeypot defense**
  1. Dynamic field names — derived from post ID + site salt so bots cannot hardcode field names  
  2. CSS-clipped honeypots — a text field and a checkbox hidden via `clip: rect()` (not `display:none`) that bots fill but humans never see  
  3. Signed time tokens — each form carries a timestamp + HMAC hash verified server-side with `hash_equals()` to prevent timing attacks. Submissions under the minimum threshold are rejected  
  4. JavaScript verification — a hidden token is injected after a configurable delay. Static crawlers and instant headless browsers miss it; real users don't  
  5. Block REST API Comments — optional setting to reject comments posted directly through the `wp/v2/comments` REST endpoint, which the classic form-based layers cannot cover
- **Soft-kill responses** — spam bots get HTTP 200 OK, deceiving them into thinking the comment was posted successfully
- **Logged-in user bypass** — authenticated users skip verification by default; enable **Apply to Logged-in Users** if your site has open registration or untrusted members
- **Configurable thresholds** — adjust minimum submit time and JS injection delay to match your audience
- **Full filter API** — customize every layer via WordPress filters
- **Lightweight codebase** — backend-only, no frontend assets, fast by design
- **Safe-by-default** — doesn't override core behavior unless a check fails

## How It Works

1. A visitor loads a post with a comment form → the plugin injects dynamic honeypot fields, a signed time token, and a delayed JS verification token  
2. A human reads the article, scrolls, then comments — naturally spending more time than the minimum threshold  
3. On submit, the server validates:  
   - Honeypot fields are empty (Layer 2)  
   - Time token is valid and enough time has passed (Layer 3)  
   - JS token is present and correct (Layer 4)  
4. If any layer fails → the comment is silently discarded with HTTP 200 OK  
5. (Optional) If **Block REST API Comments** is enabled, direct POSTs to `wp/v2/comments` are rejected entirely — closing the gap the classic form layers can't see

No friction for humans. No clues for bots.

## Settings

Navigate to:

```
Settings → Init Void Shield
```

Available fields:

- **Enable / Disable** — master toggle for the entire plugin  
- **Apply to Logged-in Users** — force verification even for authenticated users  
- **Minimum Submit Time** — seconds a user must spend between page load and submit (default: 3)  
- **JavaScript Delay** — milliseconds before the JS token is injected into the form (default: 2000)  
- **Block REST API Comments** — reject comments posted directly to the REST API endpoint `wp/v2/comments`

## Developer Filters

### `init_plugin_suite_void_shield_salt`

Customize the site salt used to derive dynamic field names.

**Params:**  
`string $salt`

**Example:**

```php
add_filter( 'init_plugin_suite_void_shield_salt', function( $salt ) {
    return 'my-custom-secret-salt-' . $salt;
});
```

### `init_plugin_suite_void_shield_min_time`

Override the minimum submit time threshold (in seconds).

**Params:**  
`int $min_time`

**Example:**

```php
add_filter( 'init_plugin_suite_void_shield_min_time', function( $min_time ) {
    return 5; // Require at least 5 seconds
});
```

### `init_plugin_suite_void_shield_js_delay`

Override the JavaScript token injection delay (in milliseconds).

**Params:**  
`int $delay`

**Example:**

```php
add_filter( 'init_plugin_suite_void_shield_js_delay', function( $delay ) {
    return 3000; // 3 seconds
});
```

### `init_plugin_suite_void_shield_honeypot_fields`

Modify the honeypot field configuration before rendering.

**Params:**  
`array $fields`, `int $post_id`

**Example:**

```php
add_filter( 'init_plugin_suite_void_shield_honeypot_fields', function( $fields, $post_id ) {
    $fields['text']['label'] = 'Leave this empty';
    return $fields;
}, 10, 2 );
```

### `init_plugin_suite_void_shield_should_verify`

Conditionally skip verification for specific requests.

**Params:**  
`bool $should_verify`

**Example:**

```php
add_filter( 'init_plugin_suite_void_shield_should_verify', function( $should_verify ) {
    if ( is_user_logged_in() && current_user_can( 'editor' ) ) {
        return false; // Bypass for editors
    }
    return $should_verify;
});
```

### `init_plugin_suite_void_shield_spam_response`

Customize the response sent to detected spam bots.

**Params:**  
`mixed $response`

**Example:**

```php
add_filter( 'init_plugin_suite_void_shield_spam_response', function( $response ) {
    return new WP_Error( 'blocked', 'Nice try, bot.', array( 'status' => 403 ) );
});
```

## Installation

1. Upload plugin folder to `/wp-content/plugins/`
2. Activate under **Plugins → Init Void Shield**
3. Go to **Settings → Init Void Shield** to review or adjust thresholds
4. Done — your comment forms are now protected

## License

GPLv2 or later — open source, minimal, developer-first.

## Part of Init Plugin Suite

Init Void Shield is part of the [Init Plugin Suite](https://en.inithtml.com/init-plugin-suite-minimalist-powerful-and-free-wordpress-plugins/) — a collection of blazing-fast, no-bloat plugins made for WordPress developers who care about quality and speed.
