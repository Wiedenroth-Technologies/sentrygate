/**
 * SentryGate login challenge – symbol choice, no third-party SDK.
 */
(function () {
	'use strict';

	if (typeof SentryGateChallenge === 'undefined') {
		return;
	}

	var cfg = SentryGateChallenge;
	var root = document.getElementById('sentrygate-challenge');
	var tokenInput = document.getElementById('sentrygate_challenge_token');
	var wrap = document.getElementById('sentrygate-choice-wrap');

	if (!root || cfg.mode !== 'full' || !wrap) {
		return;
	}

	function markSelected() {
		var items = wrap.querySelectorAll('.sentrygate-choice__item');
		items.forEach(function (item) {
			var input = item.querySelector('input');
			item.classList.toggle('is-selected', !!(input && input.checked));
		});
	}

	function bindRefresh() {
		var refreshBtn = document.getElementById('sentrygate-refresh');
		if (!refreshBtn) {
			return;
		}

		refreshBtn.addEventListener('click', function () {
			refreshBtn.disabled = true;
			var body = new FormData();
			body.append('action', 'sentrygate_new_challenge');
			body.append('nonce', cfg.nonce);

			fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (!json || !json.success || !json.data) {
						refreshBtn.disabled = false;
						return;
					}
					if (tokenInput && json.data.token) {
						tokenInput.value = json.data.token;
					}
					if (json.data.html) {
						wrap.innerHTML = json.data.html;
						bindRefresh();
						markSelected();
					}
				})
				.catch(function () {
					refreshBtn.disabled = false;
				});
		});
	}

	wrap.addEventListener('change', markSelected);
	markSelected();
	bindRefresh();
})();
