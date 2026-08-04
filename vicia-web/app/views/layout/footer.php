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
<script src="<?= asset('js/ajax.js') ?>"></script>
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/charts.js') ?>"></script>
<?php if (!empty($pageScripts)): foreach ($pageScripts as $script): ?>
<script src="<?= asset('js/' . $script) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
