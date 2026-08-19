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
    console.log('[PWA] 🚀 Initialisation PWA commençante...');
    if (!this.isPWASupported()) {
      console.warn('[PWA] ✗ Navigateur ne supporte pas PWA (serviceWorker ou caches manquant)');
      return;
    }
    console.log('[PWA] ✓ PWA supportée');

    try {
      console.log('[PWA] → Enregistrement Service Worker...');
      await this.registerServiceWorker();
      console.log('[PWA] → Configuration notifications push...');
      this.setupPushNotifications(false);
      console.log('[PWA] → Configuration prompt d\'installation...');
      this.setupInstallPrompt();
      console.log('[PWA] → Bouton d\'installation configuré');
      this.setupInstallButton();
      console.log('[PWA] ✅ PWA entièrement initialisée');
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
    console.log('[PWA] 🔔 Configuration des notifications push...');
    if (!('Notification' in window) || !('PushManager' in window)) {
      console.warn('[PWA] ✗ Navigateur ne supporte pas Web Push (Notification ou PushManager manquant)');
      return;
    }
    console.log('[PWA] ✓ Navigateur supporte Web Push');
    console.log('[PWA] → Permission actuelle:', Notification.permission);

    if (Notification.permission === 'default' && requestPermission) {
      console.log('[PWA] → Demande de permission utilisateur...');
      try {
        const permission = await Notification.requestPermission();
        console.log('[PWA] → Réponse utilisateur:', permission);
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
      console.log('[PWA] → Service Worker ready, récupération clé VAPID...');
      const registration = await navigator.serviceWorker.ready;
      const keyResponse = await fetch(this.appUrl('/api/v1/push/public-key'), { credentials: 'same-origin' });
      console.log('[PWA] → Réponse clé VAPID:', keyResponse.status, keyResponse.statusText);
      const keyData = await keyResponse.json();
      console.log('[PWA] → Données clé:', keyData);
      const publicKey = keyData?.publicKey;

      if (!publicKey) {
        console.error('[PWA] ✗ VAPID clé publique non disponible', keyData);
        return;
      }
      console.log('[PWA] ✓ Clé VAPID reçue, abonnement push...');

      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: this.urlBase64ToUint8Array(publicKey)
      });
      console.log('[PWA] ✓ Abonnement créé, endpoint:', subscription.endpoint);

      const houseId = Number(document.body.dataset.houseId || 0) || null;
      console.log('[PWA] → Envoi abonnement au serveur (house_id:', houseId + ')');
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
      
      console.log('[PWA] → Réponse serveur:', response.status, response.statusText);
      if (!response.ok) {
        const errorText = await response.text();
        throw new Error('Push subscription failed: ' + response.statusText + ' - ' + errorText);
      }

      console.log('[PWA] ✅ Abonnement push enregistré avec succès');
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

  /**
   * Enregistrer le Service Worker
   */
  async registerServiceWorker() {
    try {
      const serviceWorkerUrl = this.appUrl('/assets/js/sw.js');
      const scope = this.appUrl('/');
      console.log('[PWA] → Enregistrement du Service Worker (' + serviceWorkerUrl + ')...');
      this.registration = await navigator.serviceWorker.register(serviceWorkerUrl, {
        scope
      });
      console.log('[PWA] ✓ Service Worker enregistré:', this.registration);

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
      this.showInstallPrompt();
      const installButton = document.getElementById('pwa-install-button');
      if (installButton) installButton.style.display = 'inline-flex';
    });

    window.setTimeout(() => this.showInstallPrompt(), 1200);

    window.addEventListener('appinstalled', () => {
      console.log('[PWA] App installed');
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
      window.alert('Dans le menu du navigateur, choisissez « Installer l’application » ou « Ajouter à l’écran d’accueil ».');
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
