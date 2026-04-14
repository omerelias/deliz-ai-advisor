/**
 * Deliz AI Advisor — frontend chat widget.
 * Vanilla JS (ES2020). No jQuery, no build step.
 *
 * Popup-driven integration:
 * Themes like deliz-short have NO single-product page — products open in a
 * popup (#ed-product-popup). We observe body.popup-open and the popup DOM,
 * read the current product_id from the popup, and bind the chat to it.
 */
(function () {
	'use strict';

	const cfg  = window.delizAi || {};
	const root = document.getElementById('deliz-ai-widget');
	if (!root) return;

	// ---- Element refs ----
	const bubble     = root.querySelector('.deliz-ai-bubble');
	const panel      = root.querySelector('.deliz-ai-panel');
	const closeBtn   = root.querySelector('.deliz-ai-panel__close');
	const messagesEl = root.querySelector('.deliz-ai-panel__messages');
	const form       = root.querySelector('.deliz-ai-panel__input');
	const input      = root.querySelector('.deliz-ai-input');
	const sendBtn    = root.querySelector('.deliz-ai-send');
	const suggestionsTemplate = document.getElementById('deliz-ai-suggestions-template');

	// ---- Session + state ----
	const STORAGE_KEY = 'deliz_ai_session_v1';
	const HISTORY_PREFIX = 'deliz_ai_history_';
	const state = {
		sessionId: getOrCreateSessionId(),
		productId: parseInt(root.dataset.productId || '0', 10),
		history: [],
		busy: false,
	};

	// Initial visibility: if we already have a product (real WC page), show.
	// Otherwise hide until the popup opens.
	if (state.productId > 0) {
		showBubble();
		loadHistoryForProduct(state.productId);
	}

	// ---- Event wiring ----
	bubble.addEventListener('click', openPanel);
	closeBtn.addEventListener('click', closePanel);
	form.addEventListener('submit', onSubmit);
	messagesEl.addEventListener('click', onMessagesClick);

	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape' && root.classList.contains('is-open')) {
			closePanel();
		}
	});

	// ---- Popup observer (the magic for popup-driven themes) ----
	initPopupObserver();

	/**
	 * Watch for product popup opening/closing and pivot the chat accordingly.
	 * The theme toggles `popup-open` on <body> and injects #ed-product-popup.
	 */
	function initPopupObserver() {
		// Initial check (in case popup already exists at DOMContentLoaded).
		syncWithPopup();

		// Class changes on <body>.
		const bodyObs = new MutationObserver(syncWithPopup);
		bodyObs.observe(document.body, { attributes: true, attributeFilter: ['class'] });

		// New popup node appended / removed.
		const docObs = new MutationObserver((mutations) => {
			for (const m of mutations) {
				if (m.addedNodes.length || m.removedNodes.length) {
					syncWithPopup();
					break;
				}
			}
		});
		docObs.observe(document.body, { childList: true });
	}

	function syncWithPopup() {
		const popup = document.getElementById('ed-product-popup');
		const bodyOpen = document.body.classList.contains('popup-open');

		if (popup && (popup.classList.contains('is-open') || bodyOpen)) {
			// Popup is open — read product id and bind.
			const pid = readProductIdFromPopup(popup);
			if (pid > 0) {
				bindToProduct(pid);
				showBubble();
			}
		} else if (state.productId > 0 && !isRealProductPage()) {
			// Popup closed and we're not on a real product page — hide bubble,
			// close panel if open.
			closePanel();
			hideBubble();
		}
	}

	function readProductIdFromPopup(popup) {
		// Priority 1: the theme's own state object.
		try {
			if (window.EDProductPopupState && window.EDProductPopupState.popupData) {
				const id = parseInt(window.EDProductPopupState.popupData.id, 10);
				if (id > 0) return id;
			}
		} catch (e) { /* ignore */ }

		// Priority 2: the add-to-cart button's data attribute.
		const btn = popup.querySelector('#popup-add-to-cart[data-product-id]');
		if (btn) {
			const id = parseInt(btn.getAttribute('data-product-id'), 10);
			if (id > 0) return id;
		}

		// Priority 3: any data-product-id on the popup root.
		if (popup.dataset.productId) {
			const id = parseInt(popup.dataset.productId, 10);
			if (id > 0) return id;
		}

		return 0;
	}

	function isRealProductPage() {
		return document.body.classList.contains('single-product') &&
			parseInt(root.dataset.productId || '0', 10) > 0;
	}

	function bindToProduct(productId) {
		if (productId === state.productId) return; // Nothing changed.

		state.productId = productId;
		root.dataset.productId = String(productId);

		// Reset visible messages; rehydrate history for this product.
		messagesEl.innerHTML = '';
		state.history = loadHistoryFromStorage(productId);
		renderInitial();
	}

	function loadHistoryForProduct(productId) {
		state.history = loadHistoryFromStorage(productId);
		messagesEl.innerHTML = '';
		renderInitial();
	}

	function renderInitial() {
		// Greeting + suggested chips.
		const greeting = root.dataset.greeting || '';
		if (greeting) {
			const el = document.createElement('div');
			el.className = 'deliz-ai-msg deliz-ai-msg--assistant deliz-ai-msg--greeting';
			el.textContent = greeting;
			messagesEl.appendChild(el);
		}
		if (suggestionsTemplate && !state.history.length) {
			const clone = suggestionsTemplate.content.cloneNode(true);
			messagesEl.appendChild(clone);
		}
		// Previous turns (if any).
		for (const turn of state.history) {
			appendMessage(turn.role, turn.content, null, { skipSave: true });
		}
		scrollToBottom();
	}

	// ---- Visibility toggles ----
	function showBubble() {
		root.classList.remove('deliz-ai-widget--hidden');
		root.removeAttribute('hidden');
	}
	function hideBubble() {
		root.classList.add('deliz-ai-widget--hidden');
		root.setAttribute('hidden', 'hidden');
	}

	// ---- Panel transitions ----
	function openPanel() {
		if (!state.productId) return; // Nothing to talk about yet.
		root.classList.add('is-open');
		panel.hidden = false;
		if (!messagesEl.children.length) {
			renderInitial();
		}
		sessionStorage.setItem('deliz_ai_seen', '1');
		requestAnimationFrame(() => input && input.focus());
	}
	function closePanel() {
		root.classList.remove('is-open');
		panel.hidden = true;
	}

	// ---- Chip delegation ----
	function onMessagesClick(e) {
		const chip = e.target.closest('.deliz-ai-chip');
		if (chip && chip.dataset.question) {
			sendMessage(chip.dataset.question);
			// Remove chip group after first use.
			const group = chip.closest('.deliz-ai-suggestions');
			if (group) group.remove();
		}
	}

	// ---- Sending ----
	async function onSubmit(e) {
		e.preventDefault();
		const text = (input.value || '').trim();
		if (!text) return;
		input.value = '';
		await sendMessage(text);
	}

	async function sendMessage(text) {
		if (state.busy) return;
		if (!state.productId) return;
		state.busy = true;
		toggleSend(true);

		appendMessage('user', text);
		const typingNode = appendTyping();

		try {
			const res = await fetch(cfg.restUrl + 'chat', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce,
				},
				body: JSON.stringify({
					message: text,
					product_id: state.productId,
					session_id: state.sessionId,
					history: state.history.slice(0, -1),
				}),
			});

			typingNode.remove();

			if (!res.ok) {
				const body = await safeJson(res);
				handleError(res.status, body);
				return;
			}

			const data = await res.json();
			appendMessage('assistant', data.reply || '', data.message_id);
		} catch (err) {
			typingNode.remove();
			appendError(cfg.i18n?.error || 'Error', () => sendMessage(text));
		} finally {
			state.busy = false;
			toggleSend(false);
			scrollToBottom();
		}
	}

	function handleError(status, body) {
		let msg = (body && (body.message || body.code)) || cfg.i18n?.error || 'Error';
		if (status === 429) msg = cfg.i18n?.rate_limit || msg;
		if (status === 503) msg = cfg.i18n?.daily_cap || msg;
		appendError(msg);
	}

	// ---- Rendering ----
	function appendMessage(role, text, messageId, opts) {
		opts = opts || {};
		const el = document.createElement('div');
		el.className = 'deliz-ai-msg deliz-ai-msg--' + role;
		el.textContent = text;
		messagesEl.appendChild(el);

		if (role === 'assistant' && cfg.showFeedback && messageId) {
			const fb = document.createElement('div');
			fb.className = 'deliz-ai-feedback';
			const yes = document.createElement('button');
			yes.type = 'button';
			yes.textContent = '👍';
			yes.title = cfg.i18n?.helpful || 'Helpful';
			yes.addEventListener('click', () => submitFeedback(messageId, true, yes, no));
			const no = document.createElement('button');
			no.type = 'button';
			no.textContent = '👎';
			no.title = cfg.i18n?.not_helpful || 'Not helpful';
			no.addEventListener('click', () => submitFeedback(messageId, false, no, yes));
			fb.appendChild(yes);
			fb.appendChild(no);
			messagesEl.appendChild(fb);
		}

		if (!opts.skipSave) {
			state.history.push({ role, content: text });
			saveHistory();
		}
		scrollToBottom();
	}

	function appendTyping() {
		const el = document.createElement('div');
		el.className = 'deliz-ai-typing';
		el.innerHTML = '<span></span><span></span><span></span>';
		messagesEl.appendChild(el);
		scrollToBottom();
		return el;
	}

	function appendError(text, retryFn) {
		const box = document.createElement('div');
		box.className = 'deliz-ai-error';
		box.textContent = text;
		if (retryFn) {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.textContent = cfg.i18n?.retry || 'Retry';
			btn.addEventListener('click', () => {
				box.remove();
				retryFn();
			});
			box.appendChild(document.createElement('br'));
			box.appendChild(btn);
		}
		messagesEl.appendChild(box);
		scrollToBottom();
	}

	function scrollToBottom() { messagesEl.scrollTop = messagesEl.scrollHeight; }
	function toggleSend(busy)  { sendBtn.disabled = busy; input.disabled = busy; }

	async function submitFeedback(messageId, helpful, clicked, other) {
		clicked.classList.add('is-selected');
		other.classList.remove('is-selected');
		try {
			await fetch(cfg.restUrl + 'feedback', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
				body: JSON.stringify({ message_id: messageId, helpful }),
			});
		} catch (e) { /* silent */ }
	}

	// ---- Storage ----
	function getOrCreateSessionId() {
		let id = sessionStorage.getItem(STORAGE_KEY);
		if (!id) {
			id = uuidv4();
			sessionStorage.setItem(STORAGE_KEY, id);
		}
		try {
			const maxAge = 30 * 60;
			document.cookie = 'deliz_ai_sid=' + encodeURIComponent(id)
				+ '; path=/; max-age=' + maxAge + '; SameSite=Lax';
		} catch (e) { /* ignore */ }
		return id;
	}
	function loadHistoryFromStorage(productId) {
		try {
			const raw = sessionStorage.getItem(HISTORY_PREFIX + productId);
			return raw ? JSON.parse(raw) : [];
		} catch (e) { return []; }
	}
	function saveHistory() {
		try {
			sessionStorage.setItem(
				HISTORY_PREFIX + state.productId,
				JSON.stringify(state.history.slice(-20))
			);
		} catch (e) { /* ignore */ }
	}

	async function safeJson(res) { try { return await res.json(); } catch (e) { return {}; } }

	function uuidv4() {
		if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
			const r = (Math.random() * 16) | 0;
			const v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
	}
})();
