(() => {
	'use strict';

	const configElement = document.getElementById('ai-config');
	const config = configElement ? JSON.parse(configElement.textContent || '{}') : {};
	const form = document.getElementById('ai-form');
	const input = document.getElementById('ai-text-input');
	const messages = document.getElementById('ai-messages');
	const status = document.getElementById('ai-status');
	const resetButton = document.getElementById('ai-reset-btn');

	if (!form || !input || !messages || typeof ViciaAjax === 'undefined') return;

	const appendMessage = (role, text) => {
		const wrapper = document.createElement('div');
		wrapper.className = `ai-message ai-message--${role}`;
		const bubble = document.createElement('div');
		bubble.className = 'ai-message__bubble';
		bubble.textContent = text;
		wrapper.appendChild(bubble);
		messages.appendChild(wrapper);
		messages.scrollTop = messages.scrollHeight;
	};

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const message = input.value.trim();
		if (!message) return;

		appendMessage('user', message);
		input.value = '';
		input.disabled = true;
		form.querySelector('button[type="submit"]').disabled = true;
		status.textContent = 'Réponse en cours…';

		try {
			const response = await ViciaAjax.post(config.sendUrl || '/ai/message', { message });
			appendMessage('assistant', response.reply || response.message || 'Je n’ai reçu aucune réponse.');
		} catch (error) {
			appendMessage('assistant', error.message || 'Je ne peux pas répondre pour le moment.');
		} finally {
			input.disabled = false;
			form.querySelector('button[type="submit"]').disabled = false;
			status.textContent = '';
			input.focus();
		}
	});

	resetButton?.addEventListener('click', async () => {
		resetButton.disabled = true;
		try {
			await ViciaAjax.post(config.resetUrl || '/ai/reset', {});
			messages.replaceChildren();
			appendMessage('assistant', 'Bonjour ! Je suis l’assistant Vicia Home. Comment puis-je vous aider ?');
		} catch (error) {
			status.textContent = error.message || 'Impossible de démarrer une nouvelle conversation.';
		} finally {
			resetButton.disabled = false;
			input.focus();
		}
	});

	input.focus();
})();
