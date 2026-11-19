# SentryGate – Testen

Dieses Dokument beschreibt lokale End-to-End-Tests mit `@wordpress/env` und Playwright sowie die manuelle Checkliste vor einer Einreichung ins WordPress-Plugin-Verzeichnis.

## Voraussetzungen

- Node.js 20+
- Docker (für `@wordpress/env`)
- Optional: PHP 8.1+ und WP-CLI mit `wp-cli/dist-archive-command` für die Release-ZIP

```bash
npm ci
npx playwright install chromium
```

## Lokale Umgebung starten

```bash
npm run env:start
```

Standard (wp-env):

| | |
|---|---|
| URL | http://localhost:8888 |
| Benutzer | `admin` |
| Passwort | `password` |

Das Lifecycle-Skript `tests/e2e/bin/after-start.js` aktiviert SentryGate, setzt Permalinks und Test-Defaults (u. a. `challenge_min_time=0`).

WordPress-Version überschreiben (wie in CI):

```bash
WP_ENV_CORE=WordPress/WordPress#tags/6.4 npm run env:start
# bzw.
WP_ENV_CORE=WordPress/WordPress#tags/7.1 npm run env:start
```

Stoppen / neu aufsetzen:

```bash
npm run env:stop
npm run env:destroy   # Container + Volumes entfernen
```

## Playwright E2E

```bash
npm run test:e2e
# optional:
npm run test:e2e:headed
npm run test:e2e:ui
```

Abgedeckt:

1. Aktivierung / Deaktivierung
2. Login-Challenge (besteht) und Lockout nach Fehlversuchen
3. 2FA per TOTP-App (Einrichtung + Login, Code wird im Test berechnet)
4. 2FA per E-Mail (Code wird über MU-Plugin abgefangen, analog Mailpit / WP Mail Logging)
5. Eigener Login-Pfad inkl. 404 auf `wp-login.php` und Wiederherstellung
6. Konfig-Export und -Import
7. Security-Header im Frontend

Hinweise zur Deterministik:

- Keine festen `waitForTimeout`-Sleeps in den Specs; Assertions und Event-Waits.
- Symbol-Challenge: Prompt-Text (`#sentrygate-choice-target`) wird mit `aria-label` der Auswahl gematcht.
- E-Mail-Codes: MU-Plugin `tests/e2e/mu-plugins/sentrygate-e2e-mail-capture.php` hängt an Core-`wp_mail` / `pre_wp_mail` / `phpmailer_init` (kein Klartext-OTP-Hook im Produktivcode) und legt Code in `sentrygate_e2e_last_code` ab. Vor dem Login-Schritt löschen die Tests zusätzlich den 1-Minuten-E-Mail-Cooldown, sonst bleibt der Versand stumm. `SENTRYGATE_E2E` ist in `.wp-env.json` gesetzt.

### Optional: Mailpit lokal

Wenn Sie Mails lieber in einer UI prüfen wollen:

1. Mailpit starten (SMTP `1025`, UI `8025`).
2. In einem MU-Plugin `phpmailer_init` auf Host/Port von Mailpit setzen (vom WordPress-Container aus oft `host.docker.internal:1025`).
3. Codes weiterhin auch über `wp option get sentrygate_e2e_last_mail --format=json` auslesen.

## Release-ZIP bauen

```bash
npm run build:zip
# → dist/wiedenroth-technologies-sentrygate.zip
# Ordnername in der ZIP = Text Domain / WP.org-Slug
```

Das Skript `bin/build-release-zip.sh` nutzt bevorzugt `wp dist-archive` (respektiert `.distignore`) und fällt sonst auf rsync+zip zurück. Mit `FORCE_RSYNC=1` erzwingen Sie den rsync-Pfad (wie in CI).

Schnell prüfen, dass keine Dev-Dateien enthalten sind:

```bash
unzip -Z1 dist/wiedenroth-technologies-sentrygate.zip | grep -E '(\.github|tests/|docs/|composer\.json|package\.json|node_modules|vendor/|\.wp-env|\.gitkeep)' && echo 'FEHLER: Dev-Dateien gefunden' || echo 'OK: keine Dev-Pfade'
```

## CI (GitHub Actions)

| Workflow | Wann | Inhalt |
|---|---|---|
| `phpcs.yml` | Push / PR | PHP-Lint 8.1–8.3 + PHPCS |
| `plugin-check.yml` | Push / PR / manuell | Release-Build + offizielles Plugin Check |
| `e2e.yml` | **nur** PR gegen `main` + `workflow_dispatch` | Playwright gegen WP 6.4 und 7.1 (Chromium, Caching, Concurrency) |

E2E läuft **nicht** bei jedem Push auf Feature-Branches, um Actions-Minuten zu sparen.

## Manuelle Checkliste vor WP-Verzeichnis-Einreichung

Mit der **fertigen Release-ZIP** (nicht dem Git-Checkout) in [WordPress Playground](https://playground.wordpress.net/) oder einer frischen WP-Installation:

1. **ZIP-Inhalt:** keine `.github/`, `tests/`, `docs/`, `composer.*`, `package*.json`, `node_modules/`, `vendor/`, `.wp-env*`.
2. **Installieren & aktivieren** ohne PHP-Fatal / Notices (WP_DEBUG an).
3. **Module:** Basis-Schutz, Login-Schutz, Browser-Schutz, Zwei-Faktor einzeln prüfen.
4. **Login-Challenge:** Symbolwahl bestehen; nach mehreren Fehlversuchen Lockout-Hinweis.
5. **2FA App:** QR/Geheimnis, Bestätigungscode, Logout, Login mit App-Code.
6. **2FA E-Mail:** Code kommt an, Setup + Login funktionieren.
7. **Login-Pfad:** eigenen Slug setzen, Permalinks speichern, `/wp-login.php` → 404, Custom-URL funktioniert.
8. **Wiederherstellung Login-Pfad** (dokumentiert):
   - Login-URL notieren (Einstellungen → Login-Adresse); oder
   - Plugin per FTP/Dateimanager umbenennen bzw. deaktivieren; oder
   - WP-CLI: `wp plugin deactivate sentrygate` bzw. Option `module_login_path` auf `false` setzen und Permalinks flushen.
9. **Export/Import** der Konfiguration (JSON).
10. **Security-Header** auf der Startseite (z. B. DevTools → Network → Response Headers).
11. **Uninstall:** Plugin löschen; Tabellen/Optionen laut `uninstall.php` entfernt.
12. **Plugin Check** lokal oder per CI-Job gegen dieselbe ZIP erneut grün.

## WP-CLI-Hilfen

```bash
npx wp-env run cli wp plugin list
npx wp-env run cli wp option get sentrygate_settings --format=json
npx wp-env run cli wp eval 'echo SentryGate_Options::get("login_slug");'
```
