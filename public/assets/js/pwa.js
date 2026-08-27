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
      console.warn('[PWA] ✗ Navigateur ne supporte pas PWA (serviceWorker ou caches manquant)');
      return;
    }

    try {
      // L'événement peut être émis très tôt par le navigateur.
      // Il faut l'écouter avant toute opération asynchrone.
      this.setupInstallPrompt();
      this.setupInstallButton();
      await this.registerServiceWorker();
      this.setupPushNotifications(false);
    } catch (error) {
      console.error('[PWA] ✗ Erreur lors de l\'initialisation:', error);
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
  async setupPushNotifications(requestPermission = false) {
    if (!('Notification' in window) || !('PushManager' in window)) {
      console.warn('[PWA] ✗ Navigateur ne supporte pas Web Push (Notification ou PushManager manquant)');
      return;
    }

    if (Notification.permission === 'default' && requestPermission) {
      try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
          console.warn('[PWA] ✗ Notifications refusées par l\'utilisateur');
          return;
        }
      } catch (error) {
        console.error('[PWA] ✗ Erreur lors de la demande de permission:', error);
        return;
      }
    }

    if (Notification.permission !== 'granted') {
      console.warn('[PWA] ✗ Permission notifications non accordée (actuelle:', Notification.permission + ')');
      return;
    }

    try {
      const registration = await navigator.serviceWorker.ready;
      const keyResponse = await fetch(this.appUrl('/api/v1/push/public-key'), { credentials: 'same-origin' });
      const keyData = await keyResponse.json();
      const publicKey = keyData?.publicKey;

      if (!publicKey) {
        console.error('[PWA] ✗ VAPID clé publique non disponible', keyData);
        return;
      }

      let subscription = await registration.pushManager.getSubscription();
      if (!subscription) {
        subscription = await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: this.urlBase64ToUint8Array(publicKey)
        });
      }

      const houseId = Number(document.body.dataset.houseId || 0) || null;
      const response = await fetch(this.appUrl('/api/v1/push/subscribe'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({
          subscription,
          house_id: houseId,
          user_agent: navigator.userAgent
        })
      });
      
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error('Push subscription failed: ' + response.statusText + ' - ' + errorText);
      }

      window.ViciaApp?.toast('Notifications activées sur cet appareil.', 'success');
    } catch (error) {
      console.error('[PWA] ✗ Erreur lors de l\'enregistrement du push:', error);
      console.error('[PWA] Détails:', error.stack);
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

  async disablePushNotifications() {
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    if (!subscription) return true;

    const response = await fetch(this.appUrl('/api/v1/push/unsubscribe'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify({ endpoint: subscription.endpoint })
    });
    if (!response.ok) throw new Error('Désabonnement impossible.');
    await subscription.unsubscribe();
    return true;
  }

  /**
   * Enregistrer le Service Worker
   */
  async registerServiceWorker() {
    try {
      const serviceWorkerUrl = this.appUrl('/sw.js');
      const scope = this.appUrl('/');
      this.registration = await navigator.serviceWorker.register(serviceWorkerUrl, {
        scope
      });

      // Vérifier les mises à jour toutes les minutes
      this.updateCheckInterval = setInterval(() => this.checkForUpdates(), 60000);

      // Écouter les mises à jour
      this.registration.addEventListener('updatefound', () => this.onUpdateFound());

    } catch (error) {
      console.error('[PWA] Service Worker registration failed:', error);
    }
  }

  appUrl(path) {
    const base = document.querySelector('meta[name="app-base-url"]')?.getAttribute('content') || '/';
    return `${base.replace(/\/+$/, '')}/${String(path).replace(/^\/+/, '')}`;
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
      const installButton = document.getElementById('pwa-install-button');
      if (installButton) installButton.style.display = 'inline-flex';
    });

    window.addEventListener('appinstalled', () => {
      this.deferredPrompt = null;
      localStorage.setItem('pwa_installed', 'true');
      document.getElementById('pwa-install-prompt')?.style.setProperty('display', 'none');
      document.getElementById('pwa-install-button')?.style.setProperty('display', 'none');
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

    // Chrome/Edge fournissent le vrai prompt d'installation via cet événement.
    // Sans lui, afficher un bouton qui ne peut installer aucune application
    // serait trompeur.
    if (!this.deferredPrompt && !this.isIosDevice()) {
      return;
    }

    // Vérifier s'il y a un conteneur pour le prompt
    const container = document.getElementById('pwa-install-prompt');
    if (!container) {
      return;
    }

    container.style.display = 'flex';
    
    const installBtn = container.querySelector('[data-pwa-install]');
    const notificationsBtn = container.querySelector('[data-pwa-notifications]');
    const cancelBtn = container.querySelector('[data-pwa-cancel]');

    container.style.display = 'flex';

    if (installBtn) {
      installBtn.onclick = () => this.installApp();
    }

    if (notificationsBtn) {
      notificationsBtn.onclick = () => this.setupPushNotifications(true);
      notificationsBtn.style.display = ('Notification' in window && 'PushManager' in window) ? 'inline-flex' : 'none';
    }

    if (cancelBtn) {
      cancelBtn.onclick = () => {
        container.style.display = 'none';
        localStorage.setItem('pwa_install_dismissed', 'true');
      };
    }
  }

  /**
   * Installer l'app
   */
  async installApp() {
    if (!this.deferredPrompt) {
      if (this.isIosDevice()) {
        window.alert('Sur iPhone/iPad : ouvrez le menu Partager, puis choisissez « Sur l’écran d’accueil ». Vicia Home sera installée comme une application et s’ouvrira sans barre d’adresse.');
      }
      return;
    }

    this.deferredPrompt.prompt();
    const { outcome } = await this.deferredPrompt.userChoice;
    
    
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

    // Le bouton est affiché lorsque beforeinstallprompt arrive. Sur iOS,
    // il reste disponible pour expliquer l'installation native du site.
    if (!this.deferredPrompt && !this.isIosDevice()) {
      installButton.style.display = 'none';
    }

    installButton.addEventListener('click', async (e) => {
      e.preventDefault();
      await this.installApp();
    });
  }

  isIosDevice() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
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
