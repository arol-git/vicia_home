<?php
/**
 * app/views/layout/sidebar.php
 *
 * Barre de navigation latérale et barre supérieure. S'appuie sur
 * $currentUser (fourni par Controller::render()) pour n'afficher les
 * sections d'administration qu'aux rôles autorisés.
 */
use App\Models\Alert;

$role = $currentUser['role'] ?? 'user';
$unreadAlerts = Alert::countUnread();
?>
<aside class="sidebar">
    <div class="sidebar__brand">
        <div class="sidebar__brand-icon"><i class="fa-solid fa-house-signal"></i></div>
        <div class="sidebar__brand-text">Vicia<span>Home</span></div>
    </div>

    <nav class="sidebar__nav">
        <a href="<?= url('/dashboard') ?>" class="sidebar__link">
            <i class="fa-solid fa-gauge-high"></i><span>Tableau de bord</span>
        </a>

        <div class="sidebar__section-label">Maison</div>
        <a href="<?= url('/rooms') ?>" class="sidebar__link">
            <i class="fa-solid fa-door-open"></i><span>Pièces</span>
        </a>
        <a href="<?= url('/equipments') ?>" class="sidebar__link">
            <i class="fa-solid fa-plug-circle-bolt"></i><span>Équipements</span>
        </a>
        <a href="<?= url('/sensors') ?>" class="sidebar__link">
            <i class="fa-solid fa-microchip"></i><span>Capteurs</span>
        </a>
        <a href="<?= url('/cameras') ?>" class="sidebar__link">
            <i class="fa-solid fa-video"></i><span>Caméras</span>
        </a>

        <div class="sidebar__section-label">Sécurité</div>
        <a href="<?= url('/security') ?>" class="sidebar__link">
            <i class="fa-solid fa-shield-halved"></i><span>Réseau &amp; cybersécurité</span>
        </a>
        <a href="<?= url('/alerts') ?>" class="sidebar__link">
            <i class="fa-solid fa-bell"></i><span>Alertes</span>
            <span class="badge-count" data-alert-badge style="<?= $unreadAlerts ? '' : 'display:none;' ?>"><?= $unreadAlerts > 9 ? '9+' : $unreadAlerts ?></span>
        </a>

        <div class="sidebar__section-label">Énergie &amp; automatisation</div>
        <a href="<?= url('/consumption') ?>" class="sidebar__link">
            <i class="fa-solid fa-bolt"></i><span>Consommation</span>
        </a>
        <a href="<?= url('/automation') ?>" class="sidebar__link">
            <i class="fa-solid fa-diagram-project"></i><span>Automatisation</span>
        </a>

        <div class="sidebar__section-label">Administration</div>
        <a href="<?= url('/history') ?>" class="sidebar__link">
            <i class="fa-solid fa-clock-rotate-left"></i><span>Historique</span>
        </a>
        <?php if ($role === 'admin'): ?>
        <a href="<?= url('/users') ?>" class="sidebar__link">
            <i class="fa-solid fa-users"></i><span>Utilisateurs</span>
        </a>
        <a href="<?= url('/settings') ?>" class="sidebar__link">
            <i class="fa-solid fa-sliders"></i><span>Paramètres</span>
        </a>
        <?php endif; ?>
        <a href="<?= url('/profile') ?>" class="sidebar__link">
            <i class="fa-solid fa-user-gear"></i><span>Mon profil</span>
        </a>
    </nav>

    <div class="sidebar__footer">
        Vicia Home &copy; <?= date('Y') ?><br>Plateforme de maison intelligente
    </div>
</aside>

<div class="content-wrapper">
    <header class="topbar">
        <div class="topbar__title"><?= e($title ?? 'Vicia Home') ?></div>
        <div class="topbar__actions">
            <button type="button" class="topbar__icon-btn" data-action="toggle-theme" title="Changer de thème">
                <i class="fa-solid fa-moon"></i>
            </button>
            <a href="<?= url('/alerts') ?>" class="topbar__icon-btn" title="Alertes">
                <i class="fa-solid fa-bell"></i>
                <?php if ($unreadAlerts): ?><span class="dot"></span><?php endif; ?>
            </a>
            <div class="topbar__user" onclick="window.location.href='<?= url('/profile') ?>'">
                <div class="topbar__user-avatar"><?= e(strtoupper(substr($currentUser['name'] ?? 'U', 0, 1))) ?></div>
                <div class="topbar__user-info">
                    <span class="topbar__user-name"><?= e($currentUser['name'] ?? 'Utilisateur') ?></span>
                    <span class="topbar__user-role"><?= e(role_label($currentUser['role'] ?? 'user')) ?></span>
                </div>
            </div>
            <form action="<?= url('/logout') ?>" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="topbar__icon-btn" title="Déconnexion">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </header>
