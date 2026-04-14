/**
 * Deliz AI Advisor — admin settings JS.
 * Handles the "Test Connection" button and, in later phases, appearance live preview.
 */

(function () {
	'use strict';

	const cfg = window.delizAiAdmin || {};

	document.addEventListener('DOMContentLoaded', function () {
		initTestKey();
		initColorPickers();
		initCopyFromHebrew();
	});

	function initColorPickers() {
		if (typeof jQuery === 'undefined' || !jQuery.fn.wpColorPicker) return;
		jQuery('.deliz-ai-color').wpColorPicker();
	}

	function initCopyFromHebrew() {
		const btn = document.getElementById('deliz-copy-from-hebrew');
		if (!btn) return;
		btn.addEventListener('click', function () {
			if (!confirm('Copy Hebrew values to RU / AR / EN? This will overwrite their current values.')) return;

			// For every Hebrew field, find its sibling fields and copy.
			const form = document.querySelector('.deliz-ai-form');
			if (!form) return;

			const heFields = form.querySelectorAll('[name^="content[title_he]"], [name^="content[greeting_he]"], [name^="content[placeholder_he]"], [name="content[suggested_questions_he][]"]');

			heFields.forEach((el) => {
				const name = el.getAttribute('name');
				const match = name.match(/^content\[(title|greeting|placeholder)_he\]$/);
				if (match) {
					['ru', 'ar', 'en'].forEach((code) => {
						const tgt = form.querySelector('[name="content[' + match[1] + '_' + code + ']"]');
						if (tgt) tgt.value = el.value;
					});
				}
			});

			// Suggested questions arrays.
			const heQs = Array.from(form.querySelectorAll('[name="content[suggested_questions_he][]"]')).map(i => i.value);
			['ru', 'ar', 'en'].forEach((code) => {
				const targets = form.querySelectorAll('[name="content[suggested_questions_' + code + '][]"]');
				targets.forEach((el, i) => { el.value = heQs[i] || ''; });
			});
		});
	}

	function initTestKey() {
		const btn = document.getElementById('deliz-test-key');
		const input = document.getElementById('deliz-api-key');
		const result = document.getElementById('deliz-test-result');
		if (!btn || !input || !result) return;

		btn.addEventListener('click', async function () {
			const value = input.value.trim();
			// If user didn't type anything, ask server to test the stored key.
			const body = { api_key: value };

			result.className = 'deliz-test-result is-loading';
			result.textContent = cfg.i18n?.testing || 'Testing…';
			btn.disabled = true;

			try {
				const res = await fetch(cfg.restUrl + 'test-key', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': cfg.nonce,
					},
					body: JSON.stringify(body),
				});

				const data = await res.json();

				if (res.ok && data.ok) {
					result.className = 'deliz-test-result is-ok';
					const latency = data.latency_ms ? ` (${data.latency_ms} ms)` : '';
					result.textContent = '✓ ' + (cfg.i18n?.test_ok || 'Connection OK') + latency;
				} else {
					result.className = 'deliz-test-result is-fail';
					const err = (data && data.error) || (data && data.message) || (cfg.i18n?.test_fail || 'Connection failed');
					result.textContent = '✗ ' + err;
				}
			} catch (e) {
				result.className = 'deliz-test-result is-fail';
				result.textContent = '✗ ' + (e.message || 'Network error');
			} finally {
				btn.disabled = false;
			}
		});
	}
})();
