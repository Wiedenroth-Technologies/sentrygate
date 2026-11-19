/**
 * SentryGate form symbol challenge – selected state + refresh.
 */
(function () {
	'use strict';

	var cfg = window.SentryGateFormChallenge || {};

	function rootFrom(scope) {
		if (!scope) {
			return document.querySelector('[data-sentrygate-form-challenge]');
		}
		if (scope.getAttribute && scope.hasAttribute('data-sentrygate-form-challenge')) {
			return scope;
		}
		return scope.querySelector ? scope.querySelector('[data-sentrygate-form-challenge]') : null;
	}

	function markSelected(root) {
		if (!root) {
			return;
		}
		root.querySelectorAll('.sentrygate-choice__item').forEach(function (item) {
			var input = item.querySelector('input');
			item.classList.toggle('is-selected', !!(input && input.checked));
		});
	}

	function refresh(scope) {
		var root = rootFrom(scope);
		if (!root || !cfg.ajaxUrl || !cfg.nonce) {
			return Promise.resolve(false);
		}

		var wrap = root.querySelector('[data-sentrygate-choice-wrap]');
		var tokenInput = root.querySelector('input[name="sentrygate_challenge_token"]');
		var refreshBtn = root.querySelector('[data-sentrygate-refresh]');
		if (refreshBtn) {
			refreshBtn.disabled = true;
		}

		var body = new FormData();
		body.append('action', 'sentrygate_form_challenge');
		body.append('nonce', cfg.nonce);

		return fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) {
				return r.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data) {
					if (refreshBtn) {
						refreshBtn.disabled = false;
					}
					return false;
				}
				if (tokenInput && json.data.token) {
					tokenInput.value = json.data.token;
				}
				if (wrap && json.data.html) {
					wrap.innerHTML = json.data.html;
				}
				markSelected(root);
				return true;
			})
			.catch(function () {
				if (refreshBtn) {
					refreshBtn.disabled = false;
				}
				return false;
			});
	}

	document.addEventListener('click', function (event) {
		var btn = event.target.closest('[data-sentrygate-refresh]');
		if (!btn) {
			return;
		}
		var root = btn.closest('[data-sentrygate-form-challenge]');
		if (!root) {
			return;
		}
		event.preventDefault();
		refresh(root);
	});

	document.addEventListener('change', function (event) {
		var input = event.target;
		if (!input || input.name !== 'sentrygate_choice') {
			return;
		}
		markSelected(input.closest('[data-sentrygate-form-challenge]'));
	});

	document.querySelectorAll('[data-sentrygate-form-challenge]').forEach(markSelected);

	window.SentryGateFormChallenge = Object.assign({}, cfg, {
		refresh: refresh,
	});
})();
