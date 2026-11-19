#!/usr/bin/env bash
# Baut die Release-ZIP analog zu „wp dist-archive“ unter Beachtung von .distignore.
# Bevorzugt WP-CLI (dist-archive-command); fällt auf rsync/zip zurück.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PLUGIN_DIRNAME="${PLUGIN_DIRNAME:-sentrygate}"
OUT_DIR="${OUT_DIR:-dist}"
ZIP_NAME="${ZIP_NAME:-sentrygate.zip}"
DISTIGNORE="${DISTIGNORE:-.distignore}"

mkdir -p "$OUT_DIR"
ZIP_PATH="$OUT_DIR/$ZIP_NAME"
rm -f "$ZIP_PATH"

build_with_wp_cli() {
	if ! command -v wp >/dev/null 2>&1; then
		return 1
	fi
	if ! wp package path wp-cli/dist-archive-command >/dev/null 2>&1; then
		wp package install wp-cli/dist-archive-command:@stable >/dev/null
	fi
	wp dist-archive . "$ZIP_PATH" --plugin-dirname="$PLUGIN_DIRNAME"
}

build_with_rsync() {
	if [[ ! -f "$DISTIGNORE" ]]; then
		echo "Fehlt: $DISTIGNORE" >&2
		exit 1
	fi
	if ! command -v rsync >/dev/null 2>&1; then
		echo "Weder wp dist-archive noch rsync verfügbar." >&2
		exit 1
	fi
	if ! command -v zip >/dev/null 2>&1; then
		echo "zip ist erforderlich für den Fallback-Build." >&2
		exit 1
	fi

	STAGE="$(mktemp -d)"
	trap 'rm -rf "$STAGE"' EXIT

	# .distignore → rsync --exclude-from (eine Pattern-Zeile pro Eintrag).
	FILTER="$(mktemp)"
	# shellcheck disable=SC2064
	trap 'rm -rf "$STAGE" "$FILTER"' EXIT
	grep -vE '^\s*(#|$)' "$DISTIGNORE" > "$FILTER" || true

	mkdir -p "$STAGE/$PLUGIN_DIRNAME"
	rsync -a \
		--exclude-from="$FILTER" \
		--exclude '.git' \
		--exclude 'dist' \
		--exclude 'node_modules' \
		--exclude 'vendor' \
		./ "$STAGE/$PLUGIN_DIRNAME/"

	(
		cd "$STAGE"
		zip -qr "$ROOT/$ZIP_PATH" "$PLUGIN_DIRNAME"
	)
}

if build_with_wp_cli; then
	echo "Release-ZIP via wp dist-archive: $ZIP_PATH"
else
	echo "Hinweis: WP-CLI/dist-archive nicht verfügbar – Fallback rsync+zip."
	build_with_rsync
	echo "Release-ZIP via rsync+zip: $ZIP_PATH"
fi

echo "Größe: $(du -h "$ZIP_PATH" | awk '{print $1}')"
echo "Inhalt (Auszug):"
unzip -Z1 "$ZIP_PATH" | head -40
