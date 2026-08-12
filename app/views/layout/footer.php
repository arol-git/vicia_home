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

<!-- Bibliothèques externes autorisées par le cahier des charges -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<!-- Scripts applicatifs -->
<?php $ajaxScript = __DIR__ . '/../../../public/assets/js/ajax.js'; ?>
<script src="<?= asset('js/ajax.js') ?><?= file_exists($ajaxScript) ? '?v=' . filemtime($ajaxScript) : '' ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/charts.js') ?>"></script>
<script src="<?= asset('js/voice.js') ?>"></script>
<?php if (!empty($pageScripts)): foreach ($pageScripts as $script): ?>
<?php $scriptPath = __DIR__ . '/../../../public/assets/js/' . $script; ?>
<script src="<?= asset('js/' . $script) ?><?= file_exists($scriptPath) ? '?v=' . filemtime($scriptPath) : '' ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
