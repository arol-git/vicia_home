/**
 * assets/js/ai.js
 *
 * Interface conversationnelle du module Vicia Home AI : capture
 * vocale (Web Speech API), synthèse de la réponse (SpeechSynthesis
 * API), et échange texte via AJAX avec AIController::send(). Aucune
 * dépendance externe, JavaScript natif comme le reste de la
 * plateforme.
 */

document.addEventListener('DOMContentLoaded', () => {
    const config = JSON.parse(document.getElementById('ai-config')?.textContent || '{}');
    const messagesEl = document.getElementById('ai-messages');
    const statusEl = document.getElementById('ai-status');
    const form = document.getElementById('ai-form');
    const textInput = document.getElementById('ai-text-input');
    const micBtn = document.getElementById('ai-mic-btn');
    const resetBtn = document.getElementById('ai-reset-btn');

    if (!form || !messagesEl) return;

    // ------------------------- Envoi de message (texte ou vocal) -------------------------

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const text = textInput.value.trim();
        if (!text) return;
        sendMessage(text);
        textInput.value = '';
    });

    function sendMessage(text) {
        appendMessage('user', text);
        setStatus('thinking', "🧠 L'IA réfléchit...");

        ViciaAjax.post(config.sendUrl, { message: text })
            .then((res) => {
                setStatus('speaking', '🔊 Réponse...');
                const reply = String(res.reply || res.message || '').trim()
                    || "Réponse vide reçue du serveur. Rechargez la page puis vérifiez storage/logs/app.log.";
                appendMessage('assistant', reply);
                speak(res.spoken_text || reply);
                setTimeout(() => setStatus(null, ''), 600);
            })
            .catch((err) => {
                setStatus(null, '');
                appendMessage('assistant', '⚠️ ' + (err.message || "Une erreur est survenue."));
            });
    }

    function appendMessage(role, content) {
        const el = document.createElement('div');
        el.className = `ai-message ai-message--${role === 'user' ? 'user' : 'assistant'}`;
        const bubble = document.createElement('div');
        bubble.className = 'ai-message__bubble';
        bubble.innerHTML = escapeHtml(String(content || '')).replace(/\n/g, '<br>');
        el.appendChild(bubble);
        messagesEl.appendChild(el);
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

    // ------------------------------- Nouvelle conversation -------------------------------

    resetBtn?.addEventListener('click', () => {
        if (!confirm('Démarrer une nouvelle conversation ? L\'historique affiché sera effacé.')) return;
        ViciaAjax.post(config.resetUrl).then(() => {
            messagesEl.innerHTML = '';
            appendMessage('assistant', "Nouvelle conversation. Comment puis-je vous aider ?");
        });
    });

    // ------------------------------ Reconnaissance vocale ------------------------------

    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognizer = null;
    let listening = false;

    if (SpeechRecognition) {
        recognizer = new SpeechRecognition();
        recognizer.lang = 'fr-FR';
        recognizer.continuous = false;
        recognizer.interimResults = false;

        recognizer.addEventListener('start', () => {
            listening = true;
            micBtn.classList.add('is-listening');
            setStatus('listening', "🎙️ L'IA écoute...");
        });

        recognizer.addEventListener('result', (event) => {
            const transcript = event.results[0][0].transcript;
            textInput.value = transcript;
            sendMessage(transcript);
            textInput.value = '';
        });

        recognizer.addEventListener('end', () => {
            listening = false;
            micBtn.classList.remove('is-listening');
            if (statusEl.classList.contains('is-listening')) {
                setStatus(null, '');
            }
        });

        recognizer.addEventListener('error', () => {
            listening = false;
            micBtn.classList.remove('is-listening');
            setStatus(null, '');
        });

        micBtn.addEventListener('click', () => {
            if (listening) {
                recognizer.stop();
            } else {
                recognizer.start();
            }
        });
    } else {
        micBtn.disabled = true;
        micBtn.title = 'Reconnaissance vocale indisponible sur ce navigateur';
        micBtn.querySelector('.ai-mic-btn__label').textContent = 'Voix indisponible';
    }

    // ----------------------------------- Synthèse vocale -----------------------------------

    function speak(text) {
        if (!('speechSynthesis' in window) || !text) return;
        window.speechSynthesis.cancel(); // interrompt toute lecture précédente en cours
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'fr-FR';
        utterance.rate = 0.95;
        utterance.pitch = 1.0;
        utterance.voice = preferredFrenchVoice();
        window.speechSynthesis.speak(utterance);
    }

    function preferredFrenchVoice() {
        const voices = window.speechSynthesis.getVoices();
        return voices.find((voice) => voice.lang === 'fr-FR' && /google|microsoft|denise|hortense|amelie/i.test(voice.name))
            || voices.find((voice) => voice.lang === 'fr-FR')
            || voices.find((voice) => voice.lang?.startsWith('fr'))
            || null;
    }

    if ('speechSynthesis' in window) {
        window.speechSynthesis.onvoiceschanged = preferredFrenchVoice;
    }
});
