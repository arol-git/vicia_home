<?php
/**
 * app/views/layout/sidebar.php
 *
 * Barre de navigation latérale et barre supérieure. S'appuie sur
 * $currentUser (fourni par Controller::render()) pour n'afficher les
 * sections d'administration qu'aux rôles autorisés, et sur la maison
 * actuellement sélectionnée (App\Core\Auth::currentHouseId()) pour
 * scoper le compteur d'alertes et le sélecteur de maison.
 */
use App\Core\Auth;
use App\Models\Alert;
use App\Models\House;

$role = $currentUser['role'] ?? 'user';
$sidebarHouses = House::forUser($currentUser['id'], $role);
$currentHouseId = Auth::currentHouseId();
$currentHouseName = null;
foreach ($sidebarHouses as $h) {
    if ((int) $h['id'] === (int) $currentHouseId) {
        $currentHouseName = $h['name'];
        break;
    }
}
$unreadAlerts = $currentHouseId ? Alert::countUnread($currentHouseId) : 0;
?>
<aside class="sidebar">
    <div class="sidebar__brand">
        <div class="sidebar__brand-icon"><i class="fa-solid fa-house-signal"></i></div>
        <div class="sidebar__brand-text">Vicia<span>Home</span></div>
    </div>

    <?php if (!empty($sidebarHouses)): ?>
    <div class="house-switcher">
        <button type="button" class="house-switcher__current" data-toggle-house-menu>
            <i class="fa-solid fa-house"></i>
            <span><?= e($currentHouseName ?? 'Choisir une maison') ?></span>
            <i class="fa-solid fa-chevron-down"></i>
        </button>
        <div class="house-switcher__menu" id="house-switcher-menu">
            <?php foreach ($sidebarHouses as $h): ?>
                <button type="button" class="house-switcher__item <?= (int) $h['id'] === (int) $currentHouseId ? 'is-active' : '' ?>" data-switch-house data-id="<?= (int) $h['id'] ?>">
                    <?= e($h['name']) ?>
                    <?php if ((int) $h['id'] === (int) $currentHouseId): ?><i class="fa-solid fa-check"></i><?php endif; ?>
                </button>
            <?php endforeach; ?>
            <a href="<?= url('/houses') ?>" class="house-switcher__item house-switcher__manage">
                <i class="fa-solid fa-gear"></i> Gérer mes maisons
            </a>
        </div>
    </div>
    <?php endif; ?>

    <nav class="sidebar__nav">
        <a href="<?= url('/ai') ?>" class="sidebar__link is-active">
            <i class="fa-solid fa-robot"></i><span>Vicia Home AI</span>
        </a>

        <div class="sidebar__section-label">Maison</div>
        <a href="<?= url('/houses') ?>" class="sidebar__link">
            <i class="fa-solid fa-house-user"></i><span>Mes maisons</span>
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
