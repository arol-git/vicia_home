<?php
/**
 * app/views/layout/footer.php
 *
 * Pied de page HTML commun : ferme les conteneurs ouverts dans
 * header.php et sidebar.php, puis charge les scripts JavaScript de
 * l'application.
 */
?>
    </div><!-- /.content-wrapper -->
</div><!-- /.app-shell -->

<div id="pwa-install-prompt" class="pwa-install-prompt" role="dialog" aria-modal="true" aria-labelledby="pwa-install-title" style="display:none;">
    <div class="pwa-install-modal">
        <div class="pwa-install-icon"><i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i></div>
        <div>
            <h2 id="pwa-install-title" class="pwa-install-title">Installer Vicia Home</h2>
            <p class="pwa-install-description">Accédez rapidement à votre maison depuis votre téléphone.</p>
        </div>
        <div class="pwa-install-buttons">
            <button type="button" class="pwa-btn pwa-btn-primary" data-pwa-install><i class="fa-solid fa-download"></i> Installer</button>
            <button type="button" class="pwa-btn pwa-btn-primary" data-pwa-notifications><i class="fa-solid fa-bell"></i> Activer les notifications</button>
            <button type="button" class="pwa-btn pwa-btn-secondary" data-pwa-cancel>Plus tard</button>
        </div>
    </div>
</div>

<!-- Bibliothèques externes autorisées par le cahier des charges -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<!-- Scripts applicatifs -->
<?php $ajaxScript = __DIR__ . '/../../../public/assets/js/ajax.js'; ?>
<script src="<?= asset('js/ajax.js') ?><?= file_exists($ajaxScript) ? '?v=' . filemtime($ajaxScript) : '' ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/charts.js') ?>"></script>
<script src="<?= asset('js/realtime.js') ?>"></script>
<script src="<?= asset('js/voice.js') ?>"></script>
<script src="<?= asset('js/pwa.js') ?>"></script>
<?php if (!empty($pageScripts)): foreach ($pageScripts as $script): ?>
<?php $scriptPath = __DIR__ . '/../../../public/assets/js/' . $script; ?>
<script src="<?= asset('js/' . $script) ?><?= file_exists($scriptPath) ? '?v=' . filemtime($scriptPath) : '' ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
