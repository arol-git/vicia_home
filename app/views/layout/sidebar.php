<?php
/**
 * app/views/layout/sidebar.php
 *
 * Barre de navigation latérale et barre supérieure. S'appuie sur
 * $currentUser (fourni par Controller::render()) pour n'afficher les
 * sections d'administration qu'aux rôles autorisés.
 */
use App\Core\Auth;
use App\Models\Alert;
use App\Models\House;

$role = $currentUser['role'] ?? 'user';
$sidebarHouses = House::forUser($currentUser['id'], $role);
$currentHouseId = Auth::currentHouseId();
$currentHouseName = null;
foreach ($sidebarHouses as $house) {
    if ((int) $house['id'] === (int) $currentHouseId) {
        $currentHouseName = $house['name'];
        break;
    }
}
$unreadAlerts = $currentHouseId ? Alert::countUnread($currentHouseId) : 0;
?>
<aside id="main-sidebar" class="sidebar" role="navigation" aria-hidden="false">
    <div class="sidebar__brand">
        <div class="sidebar__brand-main">
            <div class="sidebar__brand-icon"><i class="fa-solid fa-house-signal"></i></div>
            <div class="sidebar__brand-text">Vicia<span>Home</span></div>
        </div>
        <button type="button" class="sidebar__close-btn" aria-label="Fermer le menu" data-close-sidebar>
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <?php if (!empty($sidebarHouses)): ?>
    <div class="house-switcher">
        <button type="button" class="house-switcher__current" data-toggle-house-menu>
            <i class="fa-solid fa-house"></i>
            <span><?= e($currentHouseName ?? 'Choisir une maison') ?></span>
            <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="house-switcher__menu" id="house-switcher-menu">
            <?php foreach ($sidebarHouses as $house): ?>
                <button type="button" class="house-switcher__item <?= (int) $house['id'] === (int) $currentHouseId ? 'is-active' : '' ?>" data-switch-house data-id="<?= (int) $house['id'] ?>">
                    <?= e($house['name']) ?>
                    <?php if ((int) $house['id'] === (int) $currentHouseId): ?><i class="fa-solid fa-check"></i><?php endif; ?>
                </button>
            <?php endforeach; ?>
            <a href="<?= url('/houses') ?>" class="house-switcher__item house-switcher__manage">
                <i class="fa-solid fa-gear"></i> Gérer mes maisons
            </a>
        </div>
    </div>
    <?php endif; ?>

    <nav class="sidebar__nav">
        <a href="<?= url('/dashboard') ?>" class="sidebar__link">
            <i class="fa-solid fa-gauge-high"></i><span>Tableau de bord</span>
        </a>
        <a href="<?= url('/ai') ?>" class="sidebar__link">
            <i class="fa-solid fa-robot"></i><span>Vicia Home AI</span>
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
        <a href="<?= url('/alerts') ?>" class="sidebar__link">
            <i class="fa-solid fa-bell"></i><span>Alertes</span>
            <span class="badge-count" data-alert-badge style="<?= $unreadAlerts ? '' : 'display:none;' ?>"><?= $unreadAlerts > 9 ? '9+' : $unreadAlerts ?></span>
        </a>

        <div class="sidebar__section-label">Automatisation</div>
        <a href="<?= url('/automation') ?>" class="sidebar__link">
            <i class="fa-solid fa-diagram-project"></i><span>Automatisation</span>
        </a>

        <div class="sidebar__section-label">Administration</div>
        <a href="<?= url('/history') ?>" class="sidebar__link">
            <i class="fa-solid fa-clock-rotate-left"></i><span>Historique</span>
        </a>
        <a href="<?= url('/houses') ?>" class="sidebar__link">
            <i class="fa-solid fa-house-user"></i><span>Mes maisons</span>
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
        <button type="button" class="topbar__menu-btn" aria-label="Ouvrir le menu" data-toggle-sidebar aria-controls="main-sidebar" aria-expanded="false">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="topbar__title"><?= e($title ?? 'Vicia Home') ?></div>
        <div class="topbar__actions">
            <a href="<?= url('/ai') ?>" class="topbar__icon-btn" title="Assistant vocal IA">
                <i class="fa-solid fa-microphone-lines"></i>
            </a>
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
            <!-- Logout moved to profile page -->
        </div>
    </header>
