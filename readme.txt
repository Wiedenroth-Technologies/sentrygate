=== SentryGate – Security Firewall, Login Protection and Two-Factor Authentication (2FA) ===
Contributors: wiedenrothtechnologies
Tags: security, 2fa, login, hardening, audit-log
Requires at least: 6.4
Tested up to: 7.2
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Wiedenroth Technologies GmbH
Author URI: https://wiedenroth.tech
Plugin URI: https://wiedenroth.tech/software/sentrygate

Lock down your WordPress login: symbol challenge, hidden login URL, two-factor authentication and an audit log. Runs on your own server.

== Description ==

SentryGate protects your WordPress site against brute-force logins, user enumeration and common attack paths. Every module can be switched on and off individually, and a setup wizard guides you through the first steps. Everything runs on your own server: no external account, no third-party tracking, no cloud dependency.

* Basic protection: blocks XML-RPC abuse, username guessing, the file editor and version leaks
* Error display check: warns when debug messages are shown on a live site (does not change wp-config.php)
* Hidden login URL: replace /wp-login.php with your own address; strangers see a 404
* Login protection: a symbol check before the password form, plus rate limiting
* Form protection for BlockForge forms: the same symbol check before submit (only available when BlockForge is active)
* Two-factor login: authenticator app or email, recovery codes and optional trusted device
* Browser protection: security headers, including Content Security Policy in report-only mode
* Files & server: clear tips for permissions and upload protection (nothing is written automatically)
* Audit log: logins, lockouts and important security events, with optional email or webhook alerts
* WordPress check: compare core files with the originals from wordpress.org (no automatic repair)

== Installation ==

1. Upload the `wiedenroth-technologies-sentrygate` folder to `/wp-content/plugins/` (or install the ZIP).
2. Activate the plugin under Plugins.
3. Follow the short setup wizard, or open SentryGate in the admin menu and turn modules on as needed. You can skip the wizard and run it again later from the overview.

== Frequently Asked Questions ==

= Does SentryGate scan for malware? =

No. SentryGate focuses on login hardening, form protection (with BlockForge), two-factor authentication and related security modules. It does not scan for malware or install automatic updates.

= Does SentryGate work alongside Orbance? =

Yes. Separate prefixes and hook namespaces avoid collisions.

= Does the login challenge need ConsentGate? =

No. It is fully self-hosted with no third-party requests. It does set a first-party functional cookie (`sentrygate_gate`) after a successful check when Login Challenge is enabled—not a third-party or tracking cookie.

= What cookies does SentryGate set? =

Only first-party, functional security cookies (HttpOnly, Secure when the site is HTTPS, SameSite=Lax):

* `sentrygate_gate` – login gate session after a passed challenge (browser session; HMAC valid up to 12 hours). Set only when the Login Challenge module is enabled.
* `sentrygate_trusted` – optional 2FA “trust this device” (lifetime = configured days, default 30). Set only when Two-Factor is enabled and the user opts in.

The form symbol challenge uses no cookies. Document these in your privacy policy as needed.

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

= 1.1.0 =
* Setup wizard for first activation (skippable, restartable from the overview)
* Admin navigation follows module switches; disabled sections redirect to the overview
* Form protection only when BlockForge is active (saved settings are kept)
* Clearer admin layout and module status on the overview
* New short description and directory readme
* Tested with WordPress 7.2

= 1.0.3 =
* Fixed admin styles and scripts not loading on the two-factor authentication setup page and the user profile page.

= 1.0.2 =
* Updated plugin name in the directory listing.

= 1.0.1 =
* Switched all source strings to English so the plugin can be translated on translate.wordpress.org.

= 1.0.0 =
* Initial WordPress.org release
* Interface hardening, custom login path, login and form challenges
* Two-factor authentication (TOTP and email) with recovery codes and trusted devices
* Security headers, audit log (email / optional webhook), core integrity check
* Config export/import and offline QR for authenticator setup

== Upgrade Notice ==

= 1.1.0 =
Admin overhaul: setup wizard, navigation follows modules, form protection only with BlockForge, and a new short description. Tested with WordPress 7.2.

= 1.0.3 =
Fixes the layout of the two-factor authentication setup page.

= 1.0.2 =
Updated plugin name in the directory listing.

= 1.0.1 =
Switched all source strings to English so the plugin can be translated on translate.wordpress.org.
