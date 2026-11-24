=== Wiedenroth Technologies SentryGate ===
Contributors: wiedenrothtechnologies
Tags: security, 2fa, login, hardening, audit-log
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Wiedenroth Technologies GmbH
Author URI: https://wiedenroth.tech
Plugin URI: https://wiedenroth.tech/software/sentrygate

Production-ready WordPress security plugin by Wiedenroth Technologies GmbH.

== Description ==

SentryGate hardens WordPress with modular protection:

* Interface hardening (XML-RPC, user enumeration, file editor, version leaks, REST whitelist)
* WP debug checks (warnings only; does not overwrite wp-config.php)
* Custom login path (404 on the original login URLs)
* Self-hosted login challenge (symbol choice / honeypot / timing / rate limit)
* Form symbol challenge for connected public forms
* Two-factor authentication (authenticator app or email, dedicated setup page, recovery codes, trusted device)
* Security headers (including CSP report-only)
* File / server hardening hints (.htaccess snippets, permission display)
* Audit log with optional IP truncation and webhook / email
* Core integrity check against api.wordpress.org

No hard third-party runtime dependencies (offline QR via an MIT library). Prefixes: sentrygate_ / SentryGate.

Not in v1: automatic updates, malware scanning, multisite network management.

== Installation ==

1. Upload the `wiedenroth-technologies-sentrygate` folder to `/wp-content/plugins/` (or install the ZIP).
2. Activate the plugin under Plugins.
3. Open SentryGate in the admin menu and configure the modules.

== Frequently Asked Questions ==

= Does SentryGate work alongside Orbance? =

Yes. Separate prefixes and hook namespaces avoid collisions.

= Does the login challenge need ConsentGate? =

No. It is fully self-hosted with no third-party requests or cookies.

= Behind Cloudflare / a reverse proxy? =

Under Login Challenge → “Trust proxy headers”, enable only if the proxy is trusted. Default: REMOTE_ADDR.

= I forgot the custom login URL =

Use the URL shown under SentryGate → Login path, rename/deactivate the plugin via FTP, or run `wp plugin deactivate wiedenroth-technologies-sentrygate` / set `module_login_path` to off and flush permalinks.

== External services ==

This plugin may connect to external services as follows:

= WordPress.org Core Checksums API =

* When: only when an administrator runs the Core Integrity check in the SentryGate admin UI.
* Where: `https://api.wordpress.org/core/checksums/1.0/`
* What is sent: the installed WordPress version and locale (no personal user data).
* Purpose: download official core file checksums and compare them with local files. No automatic repair is performed.
* Terms / privacy: https://wordpress.org/about/privacy/ and https://wordpress.org/about/license/

= Optional audit notification webhook =

* When: only if an administrator configures a webhook URL under Audit log, and a critical audit event (or lockout-burst threshold) is recorded.
* Where: the HTTPS URL entered by the site administrator (sent via `wp_safe_remote_post`).
* What is sent: JSON with event type, message, optional metadata (for example truncated IP if enabled), site home URL, and a UTC timestamp.
* Purpose: notify an external monitoring or messaging endpoint chosen by the site owner.
* Terms / privacy: controlled by the destination service configured by the administrator. SentryGate does not operate that endpoint.

== Changelog ==

= 1.0.0 =
* Initial WordPress.org release
* Interface hardening, custom login path, login and form challenges
* Two-factor authentication (TOTP and email) with recovery codes and trusted devices
* Security headers, audit log (email / optional webhook), core integrity check
* Config export/import and offline QR for authenticator setup
