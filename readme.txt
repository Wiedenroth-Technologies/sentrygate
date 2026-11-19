=== Wiedenroth Technologies SentryGate ===
Contributors: wiedenroth
Tags: security, 2fa, login, hardening, audit-log
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Wiedenroth Technologies GmbH
Author URI: https://wiedenroth.tech
Plugin URI: https://wiedenroth.tech/software/wordpress/sentrygate

Produktionsreifes WordPress-Security-Plugin von Wiedenroth Technologies GmbH.

== Description ==

SentryGate härtet WordPress modular:

* Interface-Hardening (XML-RPC, User-Enumeration, Datei-Editor, Versionsleaks, REST-Whitelist)
* WP-Debug-Kontrolle (Hinweise, kein automatisches Überschreiben von wp-config.php)
* Login-Pfad-Verschleierung (404 auf Originalpfaden)
* Self-hosted Login-Challenge (Symbolwahl/Honeypot/Timing/Rate-Limit, BFSG-tauglich)
* Formular-Symbolprüfung für angebundene öffentliche Formulare
* Zwei-Faktor (Authenticator-App oder E-Mail, eigene Einrichtungsseite, Recovery-Codes, Trusted Device)
* Security-Header (inkl. CSP Report-Only)
* Datei-/Server-Hardening (.htaccess-Snippets, Permission-Anzeige)
* Audit-Log mit optionaler IP-Kürzung und Webhook/E-Mail
* Core-Integritätscheck gegen api.wordpress.org

Keine harten Drittanbieter-Abhängigkeiten (QR offline via MIT-Bibliothek). Präfixe: sentrygate_ / SentryGate.

Nicht in v1: Auto-Updates, Malware-Scanning, Multisite-Network-Management.

== Installation ==

1. Ordner `SentryGate` nach `wp-content/plugins/` kopieren (oder als ZIP hochladen).
2. Plugin unter „Plugins“ aktivieren.
3. Unter „SentryGate“ Module und Einstellungen konfigurieren.

== Frequently Asked Questions ==

= Läuft SentryGate parallel zu Orbance? =

Ja. Eigene Präfixe und Hook-Namespaces vermeiden Kollisionen.

= Braucht die Login-Challenge ConsentGate? =

Nein – vollständig self-hosted, keine externen Requests/Cookies von Dritten.

= Hinter Cloudflare / Proxy? =

Unter Login-Challenge → „Proxy-Header vertrauen“ nur aktivieren, wenn der Proxy vertrauenswürdig ist. Standard: REMOTE_ADDR.

= Login-Adresse geändert und ausgesperrt? =

Login-Pfad-Modul in der Datenbank deaktivieren (`module_login_path` in `sentrygate_settings`) oder Plugin per FTP umbenennen/deaktivieren. Danach erneut unter dem gewünschten Slug einrichten und den Slug sicher notieren.

== Changelog ==

= 1.1.2 =
* Formular-Symbolprüfung (shared Challenge) für angebundene öffentliche Formulare
* Frontend-Login-Hooks für Challenge und 2FA (MemberGate u. a.)
* Login-Pfad: optionale Weiterleitung auf Frontend-Anmeldung
* Admin-UI- und Stabilitätsverbesserungen

= 1.0.1 =
* Frontend-Login (MemberGate): Challenge und 2FA per AJAX, ohne wp-login.php

= 1.0.0 =
* Erstveröffentlichung
* Login-Challenge nach Passwort-Auth (kein Bypass)
* Funktionierender 2FA-Flow inkl. Offline-QR, E-Mail-Code-Ablauf und Pflicht-Setup
* Rate-Limit ohne Doppelzählung; sichere IP-Ermittlung
* REST-Whitelist nur per Prefix; Config-Export/Import; Core-Checksums
