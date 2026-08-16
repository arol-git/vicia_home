/**
 * public/assets/js/pwa.js
 *
 * Gestion PWA côté client : enregistrement du Service Worker,
 * gestion des mises à jour, affichage du prompt d'installation
 */

class PWAManager {
  constructor() {
    this.registration = null;
    this.updateCheckInterval = 60000; // 1 min
    this.deferredPrompt = null;
  }

  /**
   * Initialiser le PWA
   */
  async init() {
    if (!this.isPWASupported()) {
      console.log('[PWA] Browser does not support PWA');
      return;
    }

    try {
      await this.registerServiceWorker();
      this.setupPushNotifications();
      this.setupInstallPrompt();
      this.setupInstallButton();
    } catch (error) {
      console.error('[PWA] Initialization failed:', error);
    }
  }

  /**
   * Vérifier le support PWA du navigateur
   */
  isPWASupported() {
    return 'serviceWorker' in navigator && 'caches' in window;
  }

  /**
   * Activer les notifications push du navigateur si l'utilisateur le
   * permet. La clé publique VAPID est récupérée depuis l'API interne.
   */
  async setupPushNotifications() {
    if (!('Notification' in window) || !('PushManager' in window)) {
      console.log('[PWA] Browser does not support Web Push');
      return;
    }

    if (Notification.permission === 'default') {
      try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
          console.log('[PWA] Push denied by user');
          return;
        }
      } catch (error) {
        console.warn('[PWA] Push permission request failed:', error);
        return;
      }
    }

    if (Notification.permission !== 'granted') {
      return;
    }

    try {
      const registration = await navigator.serviceWorker.ready;
      const keyResponse = await fetch('/api/v1/push/public-key', { credentials: 'same-origin' });
      const keyData = await keyResponse.json();
      const publicKey = keyData?.publicKey;

      if (!publicKey) {
        console.warn('[PWA] No VAPID public key available');
        return;
      }

      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(publicKey)
      });

      const response = await fetch('/api/v1/push/subscribe', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
          subscription,
          house_id: Number(document.body.dataset.houseId || 0) || null,
          user_agent: navigator.userAgent
        })
      });
      
      if (!response.ok) {
        throw new Error('Push subscription failed: ' + response.statusText);
      }

      console.log('[PWA] Push subscription registered');
    } catch (error) {
      console.error('[PWA] Push registration failed:', error);
    }
  }

  urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const normalized = (base64String + padding)
      .replace(/-/g, '+')
      .replace(/_/g, '/');

    const binary = atob(normalized);
    const output = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
      output[i] = binary.charCodeAt(i);
    }
    return output;
  }

  /**
   * Enregistrer le Service Worker
   */
  async registerServiceWorker() {
    try {
      this.registration = await navigator.serviceWorker.register('/assets/js/sw.js', {
        scope: '/'
      });
      console.log('[PWA] Service Worker registered:', this.registration);

      // Vérifier les mises à jour toutes les minutes
      this.updateCheckInterval = setInterval(() => this.checkForUpdates(), 60000);

      // Écouter les mises à jour
      this.registration.addEventListener('updatefound', () => this.onUpdateFound());

    } catch (error) {
      console.error('[PWA] Service Worker registration failed:', error);
    }
  }

  /**
   * Vérifier les mises à jour du Service Worker
   */
  async checkForUpdates() {
    try {
      if (this.registration) {
        await this.registration.update();
      }
    } catch (error) {
      console.warn('[PWA] Update check failed:', error);
    }
  }

  /**
   * Nouvelle version détectée
   */
  onUpdateFound() {
    const newWorker = this.registration.installing;

    newWorker.addEventListener('statechange', () => {
      if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
        // Nouvelle version disponible
        this.showUpdateNotification();
      }
    });
  }

  /**
   * Afficher notification de mise à jour
   */
  showUpdateNotification() {
    const banner = document.createElement('div');
    banner.className = 'pwa-update-banner';
    banner.innerHTML = `
      <div class="pwa-update-content">
        <p>Une nouvelle version de Vicia Home est disponible</p>
        <div class="pwa-update-actions">
          <button class="pwa-btn pwa-btn-primary" id="pwa-update-btn">Mettre à jour</button>
          <button class="pwa-btn pwa-btn-secondary" id="pwa-dismiss-btn">Plus tard</button>
        </div>
      </div>
    `;
    document.body.appendChild(banner);

    document.getElementById('pwa-update-btn').addEventListener('click', () => {
      this.updateServiceWorker();
      banner.remove();
    });

    document.getElementById('pwa-dismiss-btn').addEventListener('click', () => {
      banner.remove();
    });

    setTimeout(() => {
      if (banner.parentNode) {
        banner.remove();
      }
    }, 30000); // Auto-dismiss après 30s
  }

  /**
   * Forcer la mise à jour du Service Worker
   */
  updateServiceWorker() {
    const newWorker = this.registration.installing || this.registration.waiting;
    
    if (newWorker) {
      newWorker.postMessage({ type: 'SKIP_WAITING' });

      let refreshed = false;
      navigator.serviceWorker.addEventListener('controllerchange', () => {
        if (!refreshed) {
          refreshed = true;
          window.location.reload();
        }
      });
    }
  }

  /**
   * Gérer le prompt d'installation
   */
  setupInstallPrompt() {
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      this.deferredPrompt = e;
      this.showInstallPrompt();
    });

    window.addEventListener('appinstalled', () => {
      console.log('[PWA] App installed');
      this.deferredPrompt = null;
      localStorage.setItem('pwa_installed', 'true');
    });
  }

  /**
   * Afficher le prompt d'installation
   */
  showInstallPrompt() {
    // Ne montrer que si l'app n'est pas déjà installée
    if (localStorage.getItem('pwa_installed')) {
      return;
    }

    // Vérifier s'il y a un conteneur pour le prompt
    const container = document.getElementById('pwa-install-prompt');
    if (!container) {
      return;
    }

    container.style.display = 'flex';
    
    const installBtn = container.querySelector('[data-pwa-install]');
    const cancelBtn = container.querySelector('[data-pwa-cancel]');

    if (installBtn) {
      installBtn.addEventListener('click', () => this.installApp());
    }

    if (cancelBtn) {
      cancelBtn.addEventListener('click', () => {
        container.style.display = 'none';
        localStorage.setItem('pwa_install_dismissed', 'true');
      });
    }
  }

  /**
   * Installer l'app
   */
  async installApp() {
    if (!this.deferredPrompt) {
      return;
    }

    this.deferredPrompt.prompt();
    const { outcome } = await this.deferredPrompt.userChoice;
    
    console.log(`[PWA] User response to install prompt: ${outcome}`);
    
    this.deferredPrompt = null;
    const container = document.getElementById('pwa-install-prompt');
    if (container) {
      container.style.display = 'none';
    }
  }

  /**
   * Configurer le bouton d'installation
   */
  setupInstallButton() {
    const installButton = document.getElementById('pwa-install-button');
    if (!installButton) {
      return;
    }

    // Masquer le bouton si l'app est déjà installée
    if (localStorage.getItem('pwa_installed')) {
      installButton.style.display = 'none';
      return;
    }

    // Masquer le bouton si pas de deferred prompt
    if (!this.deferredPrompt) {
      installButton.style.display = 'none';
    }

    installButton.addEventListener('click', async (e) => {
      e.preventDefault();
      await this.installApp();
    });
  }

  /**
   * Forcer la mise à jour du cache
   */
  async clearCache() {
    try {
      const controller = navigator.serviceWorker.controller;
      if (controller) {
        controller.postMessage({ type: 'CLEAR_CACHE' });
      }
      console.log('[PWA] Cache cleared');
    } catch (error) {
      console.error('[PWA] Cache clear failed:', error);
    }
  }

  /**
   * Désinscrire le Service Worker
   */
  async unregister() {
    try {
      if (this.registration) {
        await this.registration.unregister();
        clearInterval(this.updateCheckInterval);
        console.log('[PWA] Service Worker unregistered');
      }
    } catch (error) {
      console.error('[PWA] Unregister failed:', error);
    }
  }
}

// Initialiser PWA au chargement du DOM
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    window.pwaManager = new PWAManager();
    window.pwaManager.init();
  });
} else {
  window.pwaManager = new PWAManager();
  window.pwaManager.init();
}
