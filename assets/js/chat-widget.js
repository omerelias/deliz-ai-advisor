/**
 * Deliz AI Advisor — frontend chat widget.
 * Vanilla JS (ES2020). No jQuery, no build step.
 */
(function () {
	'use strict';

	const cfg = window.delizAi || {};
	const root = document.getElementById('deliz-ai-widget');
	if (!root) return;

	const productId = parseInt(root.dataset.productId || '0', 10);
	const lang = root.dataset.lang || 'he';

	// ---- Session state ----
	const STORAGE_KEY = 'deliz_ai_session_v1';
	const HISTORY_KEY = 'deliz_ai_history_' + productId;
	const state = {
		sessionId: getOrCreateSessionId(),
		history: loadHistory(),
		busy: false,
	};

	// ---- Element refs ----
	const bubble = root.querySelector('.deliz-ai-bubble');
	const panel = root.querySelector('.deliz-ai-panel');
	const closeBtn = root.querySelector('.deliz-ai-panel__close');
	const messagesEl = root.querySelector('.deliz-ai-panel__messages');
	const form = root.querySelector('.deliz-ai-panel__input');
	const input = root.querySelector('.deliz-ai-input');
	const sendBtn = root.querySelector('.deliz-ai-send');
	const chips = root.querySelectorAll('.deliz-ai-chip');

	// ---- Event wiring ----
	bubble.addEventListener('click', openPanel);
	closeBtn.addEventListener('click', closePanel);
	form.addEventListener('submit', onSubmit);

	chips.forEach(chip => {
		chip.addEventListener('click', () => {
			const q = chip.dataset.question;
			if (q) {
				sendMessage(q);
			}
		});
	});

	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape' && root.classList.contains('is-open')) {
			closePanel();
		}
	});

	// Rehydrate prior history if it exists.
	renderHistory();

	// First-visit nudge.
	if (!sessionStorage.getItem('deliz_ai_seen')) {
		setTimeout(() => wiggleBubble(), 5000);
	}

	// ---- UI transitions ----
	function openPanel() {
		root.classList.add('is-open');
		panel.hidden = false;
		sessionStorage.setItem('deliz_ai_seen', '1');
		requestAnimationFrame(() => input && input.focus());
	}
	function closePanel() {
		root.classList.remove('is-open');
		panel.hidden = true;
	}

	function wiggleBubble() {
		bubble.animate(
			[
				{ transform: 'rotate(0)' },
				{ transform: 'rotate(-10deg)' },
				{ transform: 'rotate(10deg)' },
				{ transform: 'rotate(0)' },
			],
			{ duration: 400, iterations: 2 }
		);
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
		state.busy = true;
		toggleSend(true);

		appendMessage('user', text);
		state.history.push({ role: 'user', content: text });
		saveHistory();

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
					product_id: productId,
					session_id: state.sessionId,
					history: state.history.slice(0, -1), // exclude the just-pushed turn
				}),
			});

			typingNode.remove();

			if (!res.ok) {
				const errBody = await safeJson(res);
				handleError(res.status, errBody);
				return;
			}

			const data = await res.json();
			const reply = data.reply || '';
			appendMessage('assistant', reply, data.message_id);
			state.history.push({ role: 'assistant', content: reply });
			saveHistory();
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
	function appendMessage(role, text, messageId) {
		const el = document.createElement('div');
		el.className = 'deliz-ai-msg deliz-ai-msg--' + role;
		el.textContent = text;
		messagesEl.appendChild(el);

		// Feedback row for assistant messages (when enabled and we have a message_id)
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

	function renderHistory() {
		if (!state.history.length) return;
		// Remove greeting-only state (assistant greeting stays, we just append prior turns after it).
		for (const turn of state.history) {
			appendMessage(turn.role, turn.content);
		}
	}

	function scrollToBottom() {
		messagesEl.scrollTop = messagesEl.scrollHeight;
	}

	function toggleSend(busy) {
		sendBtn.disabled = busy;
		input.disabled = busy;
	}

	async function submitFeedback(messageId, helpful, clickedBtn, otherBtn) {
		clickedBtn.classList.add('is-selected');
		otherBtn.classList.remove('is-selected');
		try {
			await fetch(cfg.restUrl + 'feedback', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce,
				},
				body: JSON.stringify({ message_id: messageId, helpful }),
			});
		} catch (e) { /* silent */ }
	}

	// ---- Storage helpers ----
	function getOrCreateSessionId() {
		let id = sessionStorage.getItem(STORAGE_KEY);
		if (!id) {
			id = uuidv4();
			sessionStorage.setItem(STORAGE_KEY, id);
		}
		return id;
	}
	function loadHistory() {
		try {
			const raw = sessionStorage.getItem(HISTORY_KEY);
			return raw ? JSON.parse(raw) : [];
		} catch (e) { return []; }
	}
	function saveHistory() {
		try {
			sessionStorage.setItem(HISTORY_KEY, JSON.stringify(state.history.slice(-20)));
		} catch (e) { /* ignore */ }
	}

	async function safeJson(res) {
		try { return await res.json(); } catch (e) { return {}; }
	}

	function uuidv4() {
		if (crypto && crypto.randomUUID) return crypto.randomUUID();
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
			const r = (Math.random() * 16) | 0;
			const v = c === 'x' ? r : (r & 0x3) | 0x8;
			return v.toString(16);
		});
	}
})();
