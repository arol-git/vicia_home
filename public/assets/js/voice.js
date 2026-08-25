/**
 * public/assets/js/voice.js
 *
 * Module d'assistante vocale indépendante utilisant l'API Web Speech
 * native du navigateur. Gère la reconnaissance vocale, l'interface modale,
 * et la communication avec l'endpoint conversationnel backend /ai/message.
 */

const VoiceAssistant = (() => {
    let recognition = null;
    let isListening = false;
    let iFab = null;
    let modalOverlay = null;
    let modal = null;
    let statusEl = null;
    let messageEl = null;
    let waveformEl = null;
    let actionsEl = null;

    const STATE = {
        IDLE: 'idle',
        LISTENING: 'listening',
        PROCESSING: 'processing',
        SUCCESS: 'success',
        ERROR: 'error',
    };

    /**
     * Initialise le composant vocal (bouton + modal).
     * Appelé une seule fois au chargement de la page.
     */
    const init = () => {
        createUI();
        attachEventListeners();

        // Vérifier le support Web Speech API
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            console.warn('[VoiceAssistant] Web Speech API non supportée sur ce navigateur.');
            statusEl.textContent = 'Navigateur non compatible';
            showMessage('Votre navigateur ne prend pas en charge la reconnaissance vocale Web Speech API. Essayez Chrome ou Edge.', 'error');

            const startBtn = modal.querySelector('.voice-start-btn');
            if (startBtn) {
                startBtn.disabled = true;
                startBtn.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Non compatible';
            }
            return;
        }

        recognition = new SpeechRecognition();
        setupRecognition();
        console.log('[VoiceAssistant] Initialisation OK');
    };

    /**
     * Configure le moteur de reconnaissance vocale.
     */
    const setupRecognition = () => {
        recognition.lang = 'fr-FR'; // Français
        recognition.continuous = false; // Une phrase à la fois
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;

        recognition.onstart = () => {
            isListening = true;
            setState(STATE.LISTENING);
        };

        recognition.onresult = (event) => {
            let transcript = '';
            let isFinal = false;

            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
                isFinal = event.results[i].isFinal;
            }

            if (isFinal && transcript.trim()) {
                handleCommand(transcript.trim());
            }
        };

        recognition.onerror = (event) => {
            console.error('[VoiceAssistant] Erreur reconnaissance vocale:', event.error);
            handleError(`Erreur: ${event.error === 'network' ? 'Vérifiez votre connexion' : 'Microphone non disponible'}`);
        };

        recognition.onend = () => {
            isListening = false;
            // Ne pas changer d'état ici : c'est handleCommand() ou handleError() qui le fera
        };
    };

    /**
     * Crée les éléments HTML (bouton FAB + modal).
     */
    const createUI = () => {
        // Bouton FAB
        iFab = document.createElement('button');
        iFab.className = 'voice-fab';
        iFab.type = 'button';
        iFab.title = 'Assistante vocale';
        iFab.innerHTML = '<i class="fa-solid fa-microphone"></i>';
        document.body.appendChild(iFab);

        // Modal overlay
        modalOverlay = document.createElement('div');
        modalOverlay.className = 'voice-modal-overlay';
        document.body.appendChild(modalOverlay);

        // Modal
        modal = document.createElement('div');
        modal.className = 'voice-modal';
        modal.innerHTML = `
            <div class="voice-modal__title">🎤 Assistante Vocale</div>
            <div class="voice-modal__subtitle">Dites-moi ce que vous voulez faire</div>

            <div class="voice-waveform is-hidden" role="status" aria-label="Ondes sonores">
                <div class="voice-waveform__bar"></div>
                <div class="voice-waveform__bar"></div>
                <div class="voice-waveform__bar"></div>
                <div class="voice-waveform__bar"></div>
                <div class="voice-waveform__bar"></div>
            </div>

            <div class="voice-status" role="status" aria-live="polite">En attente…</div>

            <div class="voice-message" role="alert">
                <span class="voice-message__text"></span>
            </div>

            <div class="voice-actions">
                <button type="button" class="voice-actions__button is-primary voice-start-btn">
                    <i class="fa-solid fa-microphone"></i> Écouter
                </button>
                <button type="button" class="voice-actions__button is-secondary voice-close-btn">
                    <i class="fa-solid fa-xmark"></i> Fermer
                </button>
            </div>
        `;
        modalOverlay.appendChild(modal);

        // Références aux éléments
        statusEl = modal.querySelector('.voice-status');
        messageEl = modal.querySelector('.voice-message');
        waveformEl = modal.querySelector('.voice-waveform');
        actionsEl = modal.querySelector('.voice-actions');
    };

    /**
     * Attache les event listeners.
     */
    const attachEventListeners = () => {
        if (!iFab) return;

        // Bouton FAB ouvre la modal
        iFab.addEventListener('click', openModal);

        // Bouton Fermer
        modal.querySelector('.voice-close-btn').addEventListener('click', closeModal);

        // Bouton Écouter
        modal.querySelector('.voice-start-btn').addEventListener('click', startListening);

        // Fermeture au clic extérieur
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                closeModal();
            }
        });
    };

    /**
     * Ouvre la modal et réinitialise l'état.
     */
    const openModal = () => {
        modalOverlay.classList.add('is-open');
        setState(STATE.IDLE);
        statusEl.textContent = 'Prêt à écouter…';
        clearMessage();
    };

    /**
     * Ferme la modal et arrête l'écoute.
     */
    const closeModal = () => {
        stopListening();
        modalOverlay.classList.remove('is-open');
        iFab.classList.remove('is-listening');
    };

    /**
     * Démarre la reconnaissance vocale.
     */
    const startListening = () => {
        if (isListening) return;

        clearMessage();
        setState(STATE.LISTENING);
        statusEl.textContent = '🎤 Écoute en cours…';
        iFab.classList.add('is-listening');
        waveformEl.classList.remove('is-hidden');

        const startBtn = modal.querySelector('.voice-start-btn');
        startBtn.disabled = true;

        try {
            recognition.start();
        } catch (e) {
            handleError('Erreur de démarrage du microphone');
        }
    };

    /**
     * Arrête la reconnaissance vocale.
     */
    const stopListening = () => {
        if (recognition && isListening) {
            recognition.stop();
        }
        iFab.classList.remove('is-listening');
        waveformEl.classList.add('is-hidden');
        const startBtn = modal.querySelector('.voice-start-btn');
        startBtn.disabled = false;
    };

    /**
    * Traite une phrase vocale reconnue. Le endpoint conversationnel
    * accepte aussi bien les commandes que les questions factuelles.
     */
    const handleCommand = async (transcript) => {
        stopListening();
        setState(STATE.PROCESSING);
        statusEl.textContent = '⏳ Traitement de la commande…';

        showMessage(`Vous avez dit: « ${transcript} »`, 'info');

        try {
            console.log('[VoiceAssistant] Envoi de la commande:', transcript);
            const apiUrl = buildAppUrl('/ai/message');
            
            console.log('[VoiceAssistant] API URL:', apiUrl);
            console.log('[VoiceAssistant] House ID:', window.__vicia_house_id);

            const response = await fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    message: transcript,
                    csrf_token: csrfToken(),
                }),
            });

            console.log('[VoiceAssistant] Réponse HTTP:', response.status, response.statusText);
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                const text = await response.text();
                console.error('[VoiceAssistant] Réponse non JSON:', text.slice(0, 500));
                throw new Error('Endpoint vocal introuvable ou mal routé.');
            }

            const data = await response.json();

            if (response.ok && data.success) {
                setState(STATE.SUCCESS);
                statusEl.textContent = '✅ Réponse reçue';
                const reply = data.reply || data.message || 'Je n’ai pas de réponse.';
                showMessage(reply, 'success');
                speak(data.spoken_text || reply);
                if (data.intent !== 'question') {
                    setTimeout(() => closeModal(), 2000);
                }
            } else {
                handleError(data.message || 'Commande non reconnue');
            }
        } catch (error) {
            console.error('[VoiceAssistant] Erreur API:', error);
            handleError(error.message || 'Erreur de connexion. Vérifiez votre connexion Internet.');
        }
    };

    const buildAppUrl = (path) => {
        const appBase = document.querySelector('meta[name="app-base-url"]')?.getAttribute('content') || '';
        if (appBase) {
            return appBase.replace(/\/+$/, '') + '/' + String(path).replace(/^\/+/, '');
        }

        return '/' + String(path).replace(/^\/+/, '');
    };

    const csrfToken = () => {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    };

    const speak = (text) => {
        if (!('speechSynthesis' in window) || !text) return;

        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'fr-FR';
        utterance.rate = 0.95;
        utterance.pitch = 1;

        const voices = window.speechSynthesis.getVoices();
        const frenchVoice = voices.find((voice) => voice.lang && voice.lang.toLowerCase().startsWith('fr'));
        if (frenchVoice) {
            utterance.voice = frenchVoice;
        }

        window.speechSynthesis.speak(utterance);
    };

    /**
     * Gère une erreur.
     */
    const handleError = (message) => {
        stopListening();
        setState(STATE.ERROR);
        statusEl.textContent = '❌ Erreur';
        showMessage(message, 'error');
    };

    /**
     * Change l'état visuel.
     */
    const setState = (state) => {
        statusEl.classList.remove('is-listening', 'is-processing', 'is-success', 'is-error');

        if (state === STATE.LISTENING) {
            statusEl.classList.add('is-listening');
        } else if (state === STATE.PROCESSING) {
            statusEl.classList.add('is-processing');
        } else if (state === STATE.SUCCESS) {
            statusEl.classList.add('is-success');
        } else if (state === STATE.ERROR) {
            statusEl.classList.add('is-error');
        }
    };

    /**
     * Affiche un message.
     */
    const showMessage = (text, type = 'info') => {
        const textEl = messageEl.querySelector('.voice-message__text');
        textEl.textContent = text;

        messageEl.classList.remove('is-error', 'is-success');
        if (type === 'error') {
            messageEl.classList.add('is-error');
        } else if (type === 'success') {
            messageEl.classList.add('is-success');
        }

        messageEl.classList.add('is-visible');
    };

    /**
     * Efface le message affiché.
     */
    const clearMessage = () => {
        messageEl.classList.remove('is-visible', 'is-error', 'is-success');
    };

    return {
        init,
    };
})();

// Initialiser au chargement du DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', VoiceAssistant.init);
} else {
    VoiceAssistant.init();
}
