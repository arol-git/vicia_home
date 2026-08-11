/**
 * assets/js/ai.js
 *
 * Interface conversationnelle du module Vicia Home AI : capture
 * vocale (Web Speech API), synthèse vocale (SpeechSynthesis API), et
 * échange texte via AJAX avec AIController::send().
 */

document.addEventListener('DOMContentLoaded', () => {
    const config = parseConfig('ai-config');
    const messagesEl = document.getElementById('ai-messages');
    const statusEl = document.getElementById('ai-status');
    const form = document.getElementById('ai-form');
    const textInput = document.getElementById('ai-text-input');
    const micBtn = document.getElementById('ai-mic-btn');
    const resetBtn = document.getElementById('ai-reset-btn');

    if (!form || !messagesEl || !statusEl || !config.sendUrl || !config.resetUrl) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = textInput.value.trim();
        if (!message) {
            return;
        }
        textInput.value = '';
        await sendMessage(message);
    });

    resetBtn?.addEventListener('click', async () => {
        if (!confirm("Démarrer une nouvelle conversation ? L'historique affiché sera effacé.")) {
            return;
        }
        setStatus('thinking', '🔄 Réinitialisation en cours...');
        try {
            await ViciaAjax.post(config.resetUrl);
            messagesEl.innerHTML = '';
            appendMessage('assistant', 'Nouvelle conversation. Comment puis-je vous aider ?');
            setStatus(null, '');
        } catch (error) {
            console.error('[AI] reset error', error);
            setStatus(null, '');
            appendMessage('assistant', '⚠️ Impossible de réinitialiser la conversation.');
        }
    });

    initVoiceRecognition();
    initSpeechSynthesis();

    async function sendMessage(text) {
        appendMessage('user', text);
        setStatus('thinking', "🧠 L'assistant réfléchit...");

        try {
            const response = await ViciaAjax.post(config.sendUrl, { message: text });
            if (!response || !response.success) {
                throw new Error(response?.message || 'Réponse invalide du serveur.');
            }

            const assistantText = response.reply || response.message || 'Je suis désolé, je n’ai pas de réponse pour le moment.';
            setStatus('speaking', '🔊 Réponse reçue...');
            appendMessage('assistant', assistantText);
            await speak(/** @type {string} */ (response.spoken_text || assistantText));
            setStatus(null, '');
        } catch (error) {
            console.error('[AI] erreur AJAX', error);
            setStatus(null, '');
            appendMessage('assistant', '⚠️ ' + (error.message || 'Une erreur est survenue.'));
        }
    }

    function appendMessage(role, content) {
        const messageEl = document.createElement('div');
        messageEl.className = `ai-message ai-message--${role === 'user' ? 'user' : 'assistant'}`;

        const bubble = document.createElement('div');
        bubble.className = 'ai-message__bubble';
        bubble.innerHTML = escapeHtml(content).replace(/\n/g, '<br>');

        messageEl.appendChild(bubble);
        messagesEl.appendChild(messageEl);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function setStatus(state, label) {
        statusEl.textContent = label;
        statusEl.className = 'ai-chat__status' + (state ? ` is-${state}` : '');
    }

    function parseConfig(elementId) {
        const element = document.getElementById(elementId);
        if (!element) {
            return {};
        }

        try {
            return JSON.parse(element.textContent || '{}');
        } catch (error) {
            console.error('[AI] invalid config JSON', error);
            return {};
        }
    }

    function initVoiceRecognition() {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition || !micBtn) {
            disableMic('Reconnaissance vocale indisponible sur ce navigateur ou sans HTTPS.');
            return;
        }

        const recognizer = new SpeechRecognition();
        let listening = false;

        recognizer.lang = 'fr-FR';
        recognizer.continuous = false;
        recognizer.interimResults = false;

        recognizer.addEventListener('start', () => {
            listening = true;
            micBtn.classList.add('is-listening');
            setStatus('listening', "🎙️ L'assistant écoute...");
        });

        recognizer.addEventListener('result', async (event) => {
            const transcript = event.results[0][0].transcript.trim();
            if (!transcript) {
                setStatus(null, '');
                return;
            }
            textInput.value = transcript;
            await sendMessage(transcript);
            textInput.value = '';
        });

        recognizer.addEventListener('end', () => {
            listening = false;
            micBtn.classList.remove('is-listening');
            if (statusEl.classList.contains('is-listening')) {
                setStatus(null, '');
            }
        });

        recognizer.addEventListener('error', (event) => {
            console.error('[AI] speech recognition error', event.error || event);
            listening = false;
            micBtn.classList.remove('is-listening');
            setStatus(null, '');
            appendMessage('assistant', '⚠️ Reconnaissance vocale indisponible ou interrompue.');
        });

        micBtn.addEventListener('click', () => {
            if (listening) {
                recognizer.stop();
                return;
            }
            try {
                recognizer.start();
            } catch (error) {
                console.error('[AI] cannot start recognition', error);
                appendMessage('assistant', '⚠️ Impossible de démarrer la reconnaissance vocale.');
            }
        });
    }

    function initSpeechSynthesis() {
        if (!('speechSynthesis' in window)) {
            return;
        }

        window.speechSynthesis.onvoiceschanged = () => {
            preferredFrenchVoice();
        };
    }

    function speak(text) {
        if (!('speechSynthesis' in window) || !text) {
            return Promise.resolve();
        }

        window.speechSynthesis.cancel();

        return new Promise((resolve) => {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'fr-FR';
            utterance.rate = 1.0;
            utterance.voice = preferredFrenchVoice();

            utterance.addEventListener('end', resolve);
            utterance.addEventListener('error', resolve);

            window.speechSynthesis.speak(utterance);
        });
    }

    function preferredFrenchVoice() {
        const voices = window.speechSynthesis.getVoices();
        return voices.find((voice) => voice.lang === 'fr-FR' && /google|microsoft|denise|hortense|amelie/i.test(voice.name))
            || voices.find((voice) => voice.lang === 'fr-FR')
            || voices.find((voice) => voice.lang?.startsWith('fr'))
            || null;
    }

    function disableMic(title) {
        if (!micBtn) {
            return;
        }
        micBtn.disabled = true;
        micBtn.title = title;
        micBtn.style.opacity = '0.5';
        micBtn.style.cursor = 'not-allowed';
    }
});
