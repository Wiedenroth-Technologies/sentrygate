/**
 * SentryGate admin scripts.
 */
(function () {
	'use strict';

	var btn = document.getElementById('sentrygate-run-checksum');
	if (!btn || typeof SentryGateAdmin === 'undefined') {
		return;
	}

	var i18n = SentryGateAdmin.i18n || {};
	var statusEl = document.getElementById('sentrygate-checksum-status');
	var resultEl = document.getElementById('sentrygate-checksum-result');

	btn.addEventListener('click', function () {
		btn.disabled = true;
		btn.classList.add('is-busy');
		btn.setAttribute('aria-busy', 'true');
		setStatus(i18n.running || 'Prüfung läuft…', 'busy');

		var body = new FormData();
		body.append('action', 'sentrygate_run_checksum');
		body.append('nonce', SentryGateAdmin.nonce);

		fetch(SentryGateAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (json) {
				btn.disabled = false;
				btn.classList.remove('is-busy');
				btn.removeAttribute('aria-busy');
				if (!json || !json.success) {
					var err = i18n.error || 'Fehler bei der Prüfung.';
					if (json && json.data && json.data.message) {
						err = json.data.message;
					}
					setStatus(err, 'error');
					if (resultEl) {
						resultEl.innerHTML = noticeHtml('error', escapeHtml(err), '');
					}
					return;
				}
				setStatus(i18n.done || 'Prüfung abgeschlossen', 'done');
				if (resultEl) {
					resultEl.innerHTML = renderResult(json.data || {});
				}
			})
			.catch(function () {
				btn.disabled = false;
				btn.classList.remove('is-busy');
				btn.removeAttribute('aria-busy');
				setStatus(i18n.error || 'Fehler bei der Prüfung.', 'error');
			});
	});

	function setStatus(text, state) {
		if (!statusEl) {
			return;
		}
		statusEl.textContent = text;
		statusEl.className = 'sentrygate-wt-check-status';
		if (state) {
			statusEl.classList.add('sentrygate-wt-check-status--' + state);
		}
	}

	function renderResult(d) {
		if (d.error) {
			return noticeHtml('error', escapeHtml(d.error), escapeHtml(i18n.error || ''));
		}

		var mismatch = d.mismatches || [];
		var missing = d.missing || [];
		var checked = d.checked || 0;
		var ok = !!d.ok && mismatch.length === 0 && missing.length === 0;
		var tone = ok ? 'ok' : 'warn';
		var html = '';

		html += '<section class="sentrygate-wt-card sentrygate-wt-card--flush sentrygate-wt-card--result">';
		html += '<div class="sentrygate-wt-result__banner sentrygate-wt-result__banner--' + tone + '">';
		html += '<span class="sentrygate-wt-result__icon" aria-hidden="true">' + statSvg(ok ? 'check' : 'warn') + '</span>';
		html += '<div class="sentrygate-wt-result__copy">';
		html += '<p class="sentrygate-wt-result__eyebrow">' + escapeHtml(i18n.lastTitle || 'Letztes Ergebnis') + '</p>';
		html += '<h2 class="sentrygate-wt-result__title">' + escapeHtml(ok ? (i18n.okTitle || '') : (i18n.warnTitle || '')) + '</h2>';
		html += '<p class="sentrygate-wt-result__text">' + escapeHtml(ok ? (i18n.okText || '') : (i18n.warnText || '')) + '</p>';
		html += '</div></div>';

		html += '<div class="sentrygate-wt-result__body">';
		html += '<div class="sentrygate-wt-stats">';
		html += statHtml(String(checked), i18n.statChecked || 'Dateien geprüft', 'accent', 'shield');
		html += statHtml(
			String(mismatch.length),
			i18n.statMismatch || 'Abweichend',
			mismatch.length ? 'warn' : 'ok',
			mismatch.length ? 'warn' : 'check'
		);
		html += statHtml(
			String(missing.length),
			i18n.statMissing || 'Fehlend',
			missing.length ? 'off' : 'ok',
			missing.length ? 'x' : 'check'
		);
		html += '</div>';

		if (d.time_label || d.version) {
			html += '<ul class="sentrygate-wt-result__meta">';
			if (d.time_label) {
				html += metaChipHtml(i18n.metaChecked || 'Zuletzt geprüft', d.time_label);
			}
			if (d.version) {
				html += metaChipHtml(i18n.metaWp || 'WordPress', d.version);
			}
			html += '</ul>';
		}

		html += '</div></section>';

		if (ok) {
			return html;
		}

		if (mismatch.length) {
			html += fileCardHtml(i18n.mismatchFiles || 'Abweichende Dateien', mismatch);
		}
		if (missing.length) {
			html += fileCardHtml(i18n.missingFiles || 'Fehlende Dateien', missing);
		}
		return html;
	}

	function metaChipHtml(label, value) {
		return '<li class="sentrygate-wt-result__chip">' +
			'<span class="sentrygate-wt-result__chip-label">' + escapeHtml(label) + '</span>' +
			'<span class="sentrygate-wt-result__chip-value">' + escapeHtml(value) + '</span>' +
			'</li>';
	}

	function statHtml(value, label, mod, icon) {
		var cls = 'sentrygate-wt-stat' + (mod ? ' sentrygate-wt-stat--' + mod : '');
		return '<div class="' + cls + '">' +
			'<span class="sentrygate-wt-stat__icon" aria-hidden="true">' + statSvg(icon) + '</span>' +
			'<div class="sentrygate-wt-stat__body">' +
			'<p class="sentrygate-wt-stat__label">' + escapeHtml(label) + '</p>' +
			'<p class="sentrygate-wt-stat__value">' + escapeHtml(value) + '</p>' +
			'</div></div>';
	}

	function statSvg(icon) {
		var paths = {
			shield: '<path d="M12 3 5 6v6c0 5 3.4 8.4 7 9.5 3.6-1.1 7-4.5 7-9.5V6z"/>',
			check: '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5"/>',
			warn: '<path d="M12 3 2.5 20h19L12 3z"/><path d="M12 9v5M12 17h.01"/>',
			x: '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>'
		};
		var inner = paths[icon] || paths.shield;
		return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>';
	}

	function fileCardHtml(title, files) {
		var rows = files.map(function (file) {
			return '<tr><td><code class="sentrygate-wt-file">' + escapeHtml(file) + '</code></td></tr>';
		}).join('');
		return '<div class="sentrygate-wt-card sentrygate-wt-card--flush">' +
			'<div class="sentrygate-wt-card__head"><h2 class="sentrygate-wt-card__title">' + escapeHtml(title) + '</h2></div>' +
			'<div class="sentrygate-wt-table-wrap"><table class="sentrygate-wt-table">' +
			'<thead><tr><th scope="col">' + escapeHtml(i18n.fileCol || 'Datei') + '</th></tr></thead>' +
			'<tbody>' + rows + '</tbody></table></div></div>';
	}

	function noticeHtml(type, message, title) {
		var html = '<div class="sentrygate-wt-notice sentrygate-wt-notice--' + type + '" role="status">';
		if (title) {
			html += '<p class="sentrygate-wt-notice__title">' + title + '</p>';
		}
		html += '<p class="sentrygate-wt-notice__text">' + message + '</p></div>';
		return html;
	}

	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(String(str)));
		return div.innerHTML;
	}
})();
