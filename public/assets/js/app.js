/**
 * assets/js/app.js
 *
 * Initialisation générale de l'interface : gestion du thème
 * clair/sombre, ouverture/fermeture des modales, système de
 * notifications "toast", et mise en surbrillance du lien de menu
 * actif. Ce fichier est chargé sur toutes les pages authentifiées.
 */

const ViciaApp = (() => {
    'use strict';

    /**
     * Bascule entre le mode clair et le mode sombre, et mémorise le
     * choix de l'utilisateur dans le stockage local du navigateur.
     */
    function initThemeToggle() {
        const root = document.documentElement;
        const stored = localStorage.getItem('vicia_theme');
        if (stored) {
            root.setAttribute('data-theme', stored);
        }

        const toggleButtons = document.querySelectorAll('[data-action="toggle-theme"]');
        toggleButtons.forEach((btn) => {
            updateThemeIcon(btn, root.getAttribute('data-theme'));
            btn.addEventListener('click', () => {
                const current = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', current);
                localStorage.setItem('vicia_theme', current);
                updateThemeIcon(btn, current);
            });
        });
    }

    function updateThemeIcon(btn, theme) {
        const icon = btn.querySelector('i');
        if (!icon) return;
        icon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }

    /**
     * Met en surbrillance, dans la barre latérale, le lien
     * correspondant à la page actuellement affichée.
     */
    function highlightActiveLink() {
        const links = document.querySelectorAll('.sidebar__link');
        const path = window.location.pathname.replace(/\/+$/, '');
        links.forEach((link) => {
            const linkPath = new URL(link.href).pathname.replace(/\/+$/, '');
            if (linkPath === path || (linkPath !== '' && path.startsWith(linkPath) && linkPath !== '/')) {
                link.classList.add('is-active');
            }
        });
    }

    /**
     * Affiche une notification "toast" temporaire en haut à droite
     * de l'écran.
     */
    function toast(message, type = 'success') {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const el = document.createElement('div');
        el.className = `toast is-${type}`;
        const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
        el.innerHTML = `<i class="fa-solid ${icon}"></i><span>${escapeHtml(message)}</span>`;
        container.appendChild(el);

        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(20px)';
            setTimeout(() => el.remove(), 250);
        }, 4000);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Ouvre une modale par son identifiant.
     */
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('is-open');
        }
    }

    /**
     * Ferme une modale par son identifiant, ou toute modale ouverte
     * si aucun identifiant n'est fourni.
     */
    function closeModal(id) {
        if (id) {
            document.getElementById(id)?.classList.remove('is-open');
            return;
        }
        document.querySelectorAll('.modal-overlay.is-open').forEach((m) => m.classList.remove('is-open'));
    }

    function initModalTriggers() {
        document.addEventListener('click', (e) => {
            const openTrigger = e.target.closest('[data-open-modal]');
            if (openTrigger) {
                openModal(openTrigger.getAttribute('data-open-modal'));
            }

            const closeTrigger = e.target.closest('[data-close-modal]');
            if (closeTrigger) {
                closeModal(closeTrigger.closest('.modal-overlay')?.id);
            }

            // Fermeture au clic sur l'arrière-plan sombre
            if (e.target.classList.contains('modal-overlay')) {
                e.target.classList.remove('is-open');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    /**
     * Rafraîchit périodiquement le compteur d'alertes non lues affiché
     * dans la barre supérieure et le menu latéral.
     */
    function initAlertPolling() {
        const badge = document.querySelector('[data-alert-badge]');
        if (!badge) return;

        const refresh = () => {
            ViciaAjax.get('/alerts/unread-count').then((res) => {
                if (res && typeof res.count === 'number') {
                    badge.textContent = res.count > 9 ? '9+' : res.count;
                    badge.style.display = res.count > 0 ? 'inline-flex' : 'none';
                }
            }).catch(() => {});
        };

        refresh();
        setInterval(refresh, 30000);
    }

    function initHouseSwitcher() {
        const toggle = document.querySelector('[data-toggle-house-menu]');
        const menu = document.getElementById('house-switcher-menu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            menu.classList.toggle('is-open');
        });

        document.addEventListener('click', (e) => {
            if (!menu.contains(e.target) && !toggle.contains(e.target)) {
                menu.classList.remove('is-open');
            }
        });

        menu.addEventListener('click', (e) => e.stopPropagation());

        menu.querySelectorAll('[data-switch-house]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                ViciaAjax.post(`/houses/switch/${btn.dataset.id}`)
                    .then((res) => { window.location.href = res.redirect || appUrl('/dashboard'); })
                    .catch((err) => toast(err.message || 'Sélection impossible.', 'error'));
            });
        });
    }

    function initMobileSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const toggleBtns = document.querySelectorAll('[data-toggle-sidebar]');
        if (!sidebar || toggleBtns.length === 0) return;

        // Create backdrop
        let backdrop = document.createElement('div');
        backdrop.className = 'mobile-backdrop';
        document.body.appendChild(backdrop);

        // Create small left-edge area to capture swipe-open gestures on mobile
        let edge = document.createElement('div');
        edge.className = 'edge-swipe-area';
        document.body.appendChild(edge);

        function openSidebar() {
            sidebar.classList.add('is-open');
            sidebar.setAttribute('aria-hidden', 'false');
            toggleBtns.forEach(b => b.setAttribute('aria-expanded', 'true'));
            backdrop.classList.add('is-visible');
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
            // focus management: move focus to first focusable element inside sidebar
            const focusable = sidebar.querySelector('a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])');
            if (focusable) focusable.focus();
            trapFocus(sidebar);
            // Ensure house switcher menu stays closed when opening the sidebar
            const hsMenu = sidebar.querySelector('.house-switcher__menu');
            if (hsMenu) hsMenu.classList.remove('is-open');
        }

        function closeSidebar() {
            sidebar.classList.remove('is-open');
            sidebar.setAttribute('aria-hidden', 'true');
            toggleBtns.forEach(b => b.setAttribute('aria-expanded', 'false'));
            backdrop.classList.remove('is-visible');
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
            releaseFocusTrap();
            // return focus to the first toggle button
            if (toggleBtns[0]) toggleBtns[0].focus();
        }

        toggleBtns.forEach((btn) => btn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (sidebar.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }));

        backdrop.addEventListener('click', closeSidebar);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeSidebar();
        });

        // Ensure sidebar state resets when resizing to desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth > 640) {
                sidebar.classList.remove('is-open');
                backdrop.classList.remove('is-visible');
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
            }
        });

        // Close button inside sidebar
        document.querySelectorAll('[data-close-sidebar]').forEach((btn) => btn.addEventListener('click', closeSidebar));

        // Touch handling: swipe to close on sidebar
        let startX = 0;
        let currentX = 0;
        let touchingSidebar = false;

        sidebar.addEventListener('touchstart', (e) => {
            if (!sidebar.classList.contains('is-open')) return;
            startX = e.touches[0].clientX;
            touchingSidebar = true;
            sidebar.style.transition = 'none';
        }, { passive: true });

        sidebar.addEventListener('touchmove', (e) => {
            if (!touchingSidebar) return;
            currentX = e.touches[0].clientX;
            const translateX = Math.min(0, currentX - startX);
            sidebar.style.transform = `translateX(${translateX}px)`;
            backdrop.style.opacity = String(Math.max(0, 0.45 + translateX / 300));
        }, { passive: true });

        sidebar.addEventListener('touchend', (e) => {
            if (!touchingSidebar) return;
            touchingSidebar = false;
            sidebar.style.transition = '';
            const diff = currentX - startX;
            sidebar.style.transform = '';
            backdrop.style.opacity = '';
            if (diff < -60) {
                closeSidebar();
            } else {
                // restore
                openSidebar();
            }
            startX = currentX = 0;
        });

        // Edge swipe to open
        let edgeStartX = 0;
        let edgeActive = false;
        edge.addEventListener('touchstart', (e) => {
            if (sidebar.classList.contains('is-open')) return;
            edgeStartX = e.touches[0].clientX;
            edgeActive = true;
        }, { passive: true });
        edge.addEventListener('touchmove', (e) => {
            if (!edgeActive) return;
            const x = e.touches[0].clientX;
            const delta = x - edgeStartX;
            if (delta > 30) {
                openSidebar();
                edgeActive = false;
            }
        }, { passive: true });
        edge.addEventListener('touchend', () => { edgeActive = false; edgeStartX = 0; });
    }

    // Focus trap utilities
    let _focusTrapHandler = null;
    function trapFocus(container) {
        const focusable = Array.from(container.querySelectorAll('a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])'))
            .filter(el => !el.hasAttribute('disabled'));
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        _focusTrapHandler = (e) => {
            if (e.key !== 'Tab') return;
            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        };
        document.addEventListener('keydown', _focusTrapHandler);
    }

    function releaseFocusTrap() {
        if (_focusTrapHandler) {
            document.removeEventListener('keydown', _focusTrapHandler);
            _focusTrapHandler = null;
        }
    }

    function appUrl(path) {
        const base = document.querySelector('meta[name="app-base-url"]')?.getAttribute('content') || '/';
        return `${base.replace(/\/+$/, '')}/${String(path).replace(/^\/+/, '')}`;
    }

    function init() {
        initThemeToggle();
        highlightActiveLink();
        initModalTriggers();
        initAlertPolling();
        initHouseSwitcher();
        initMobileSidebar();
    }

    document.addEventListener('DOMContentLoaded', init);

    return { toast, openModal, closeModal };
})();
