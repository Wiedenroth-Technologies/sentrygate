/**
 * Wiedenroth admin UI – styleguide helpers and notice inbox.
 */
(function () {
	'use strict';

	var cfg = window.WtAdminUi || { slug: 'pluginbase', i18n: {} };
	var ownNotice = cfg.slug + '-wt-notice';

	document.addEventListener('click', function (event) {
		var target = event.target && event.target.closest ? event.target.closest('[data-wt-copy]') : null;
		if (!target) {
			return;
		}

		var copyAttr = target.getAttribute('data-wt-copy');
		var text = (copyAttr && copyAttr !== '') ? copyAttr.trim() : (target.textContent || '').trim();
		if (!text || !navigator.clipboard) {
			return;
		}

		navigator.clipboard.writeText(text).then(function () {
			target.setAttribute('data-copied', '1');
			window.setTimeout(function () {
				target.removeAttribute('data-copied');
			}, 1200);
		});
	});

	function bootInbox() {
		var inbox = document.querySelector('[data-wt-inbox]');
		var toggle = document.querySelector('[data-wt-inbox-toggle]');
		var panel = document.querySelector('[data-wt-inbox-panel]');
		var backdrop = document.querySelector('[data-wt-inbox-backdrop]');
		var openClass = cfg.slug + '-wt-inbox-open';
		if (!inbox || !toggle || !panel || !backdrop) {
			return;
		}

		function isForeignNotice(node) {
			if (!node || node.nodeType !== 1) {
				return false;
			}
			if (panel.contains(node) || node.closest('[data-wt-inbox]')) {
				return false;
			}
			if (node.classList.contains(ownNotice)) {
				return false;
			}
			return node.matches('div.notice, div.updated, div.error');
		}

		function collect() {
			var nodes = document.querySelectorAll('#wpbody-content div.notice, #wpbody-content div.updated, #wpbody-content div.error');
			nodes.forEach(function (node) {
				if (isForeignNotice(node)) {
					panel.appendChild(node);
				}
			});
			update();
		}

		function update() {
			var count = panel.querySelectorAll('div.notice, div.updated, div.error').length;
			var label = toggle.querySelector('[data-wt-inbox-label]');
			if (count < 1) {
				toggle.hidden = true;
				panel.hidden = true;
				backdrop.hidden = true;
				toggle.setAttribute('aria-expanded', 'false');
				document.body.classList.remove(openClass);
				return;
			}

			toggle.hidden = false;
			if (label) {
				label.textContent = count === 1
					? (cfg.i18n.noticeOne || '1 Nachricht')
					: (cfg.i18n.noticeMany || '%d Nachrichten').replace('%d', String(count));
			}
		}

		function close() {
			panel.hidden = true;
			backdrop.hidden = true;
			toggle.setAttribute('aria-expanded', 'false');
			document.body.classList.remove(openClass);
		}

		function open() {
			panel.hidden = false;
			backdrop.hidden = false;
			toggle.setAttribute('aria-expanded', 'true');
			document.body.classList.add(openClass);
		}

		toggle.addEventListener('click', function (event) {
			event.preventDefault();
			if (panel.hidden) {
				open();
			} else {
				close();
			}
		});

		backdrop.addEventListener('click', close);

		document.addEventListener('click', function (event) {
			if (!panel.hidden && !inbox.contains(event.target)) {
				close();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !panel.hidden) {
				close();
			}
		});

		collect();
		window.setTimeout(collect, 0);
		window.setTimeout(collect, 80);
		if (window.jQuery) {
			window.jQuery(collect);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bootInbox);
	} else {
		bootInbox();
	}
})();
