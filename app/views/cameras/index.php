<?php
/**
 * app/views/cameras/index.php
 *
 * Vue des caméras déclarées dans le système. L'intégration du flux
 * vidéo temps réel (RTSP relayé en HLS/MJPEG, par exemple via un
 * service ffmpeg dédié) dépend du modèle de caméra effectivement
 * déployé ; cette vue prévoit l'emplacement d'intégration
 * (data-stream-url) prêt à recevoir un lecteur vidéo.
 */
use App\Core\Auth;

$pageScripts = [];
$houseRole = Auth::roleOnHouse(Auth::currentHouseId() ?? 0);
// Même règle que pour les équipements : le topic MQTT d'une caméra
// n'est visible que par l'administration.
$canSeeMqttTopics = can_view_mqtt_topics($houseRole);
?>

<div class="page-header">
    <div>
        <div class="page-header__title">Caméras</div>
        <div class="page-header__subtitle"><?= count($cameras) ?> caméra(s) déclarée(s)</div>
    </div>
</div>

<?php if (empty($cameras)): ?>
    <div class="card"><div class="empty-state"><i class="fa-solid fa-video-slash"></i><p>Aucune caméra n'est encore déclarée. Ajoutez-en une depuis le module Équipements (type « Caméra »).</p></div></div>
<?php else: ?>
<div class="grid grid-auto">
    <?php foreach ($cameras as $camera): ?>
        <div class="card">
            <div class="flex-between mb-4">
                <div class="card__title"><?= e($camera['name']) ?></div>
                <?php if ($camera['state']): ?>
                    <span class="badge badge-success"><i class="fa-solid fa-circle"></i> En ligne</span>
                <?php else: ?>
                    <span class="badge badge-neutral"><i class="fa-solid fa-circle"></i> Hors ligne</span>
                <?php endif; ?>
            </div>
            <div style="aspect-ratio:16/9; background:var(--color-navy-900); border-radius:var(--radius-md); display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.4);">
                <?php if ($camera['state']): ?>
                    <i class="fa-solid fa-video" style="font-size:2rem;"></i>
                <?php else: ?>
                    <i class="fa-solid fa-video-slash" style="font-size:2rem;"></i>
                <?php endif; ?>
            </div>
            <div class="text-sm text-muted mt-4">
                <?= e($camera['room_name']) ?>
                <?php if ($canSeeMqttTopics): ?>
                    · Topic : <?= e($camera['mqtt_topic']) ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
