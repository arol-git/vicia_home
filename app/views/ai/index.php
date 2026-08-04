<?php
/**
 * app/views/ai/index.php
 *
 * Interface de conversation du module Vicia Home AI. Charge
 * assets/css/ai.css et assets/js/ai.js (voir $pageScripts, footer.php),
 * et injecte l'historique récent en JSON pour restaurer la fenêtre de
 * discussion au chargement de la page.
 */
$pageScripts = ['ai.js'];
?>
<link rel="stylesheet" href="<?= asset('css/ai.css') ?>">

<div class="page-header">
    <div>
        <div class="page-header__title">🤖 Vicia Home AI</div>
        <div class="page-header__subtitle">Posez une question, donnez un ordre, ou demandez une analyse — à l'écrit ou à l'oral</div>
    </div>
    <button type="button" class="btn btn-secondary" id="ai-reset-btn"><i class="fa-solid fa-rotate-left"></i> Nouvelle conversation</button>
</div>

<div class="ai-chat-card">
    <div class="ai-chat__messages" id="ai-messages">
        <?php if (empty($history)): ?>
            <div class="ai-message ai-message--assistant">
                <div class="ai-message__bubble">👋 Bonjour ! Je suis l'assistant Vicia Home. Vous pouvez me parler ou m'écrire — essayez « Quelle est la température du salon ? » ou « Allume les lampes du salon ».</div>
            </div>
        <?php else: ?>
            <?php foreach ($history as $m): ?>
                <div class="ai-message ai-message--<?= $m['role'] === 'user' ? 'user' : 'assistant' ?>">
                    <?php
                    $content = $m['content'];
                    if ($m['role'] === 'assistant' && $content === "Je n'ai pas pu formuler de réponse détaillée pour le moment.") {
                        $content = "Je peux traiter les commandes et les informations de la maison, mais le moteur IA détaillé n'a pas répondu. Vérifie AI_LLM_API_KEY, le modèle configuré et la connexion internet du serveur.";
                    }
                    ?>
                    <div class="ai-message__bubble"><?= nl2br(e($content)) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="ai-chat__status" id="ai-status" aria-live="polite"></div>

    <form id="ai-form" class="ai-chat__input-row">
        <?= csrf_field() ?>
        <button type="button" id="ai-mic-btn" class="ai-mic-btn" title="Parler à l'IA" aria-label="Parler à l'IA">
            <i class="fa-solid fa-microphone"></i>
            <span class="ai-mic-btn__label">Parler</span>
        </button>
        <input type="text" id="ai-text-input" class="form-control ai-chat__text" placeholder="Écrivez un message..." autocomplete="off">
        <button type="submit" class="btn btn-primary ai-chat__send" title="Envoyer">
            <i class="fa-solid fa-paper-plane"></i>
        </button>
    </form>
</div>

<script type="application/json" id="ai-config">
<?= json_encode([
    'sendUrl'   => url('/ai/message'),
    'resetUrl'  => url('/ai/reset'),
], JSON_UNESCAPED_UNICODE) ?>
</script>
