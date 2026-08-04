/**
 * assets/js/ajax.js
 *
 * Enveloppe légère autour de l'API fetch(), transmettant
 * automatiquement le jeton CSRF et l'en-tête identifiant les
 * requêtes AJAX (X-Requested-With), et normalisant le traitement
 * des réponses JSON du back-end.
 */

const ViciaAjax = (() => {
    'use strict';

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    function baseUrl() {
        return document.querySelector('meta[name="app-base-url"]')?.getAttribute('content') || '/';
    }

    function resolveUrl(url) {
        if (/^https?:\/\//i.test(url)) {
            return url;
        }

        const base = baseUrl().replace(/\/+$/, '');
        return `${base}/${String(url).replace(/^\/+/, '')}`;
    }

    /**
     * Effectue une requête vers l'application et retourne une
     * promesse résolue avec le corps JSON de la réponse. En cas
     * d'erreur HTTP, la promesse est rejetée avec l'objet de réponse
     * JSON (contenant généralement { success: false, message }).
     */
    async function request(method, url, data = null) {
        const options = {
            method,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        };

        if (data instanceof FormData) {
            data.append('csrf_token', csrfToken());
            options.body = data;
        } else if (data !== null) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify({ ...data, csrf_token: csrfToken() });
        } else if (method !== 'GET') {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify({ csrf_token: csrfToken() });
        }

        // Le routeur PHP n'accepte nativement que GET/POST : les
        // méthodes PUT/DELETE sont envoyées en POST avec un champ
        // "_method" de substitution, conformément à app/core/Router.php.
        let finalMethod = method;
        if (method === 'PUT' || method === 'DELETE') {
            const payload = data instanceof FormData ? data : JSON.parse(options.body || '{}');
            if (payload instanceof FormData) {
                payload.append('_method', method);
            } else {
                payload._method = method;
                options.body = JSON.stringify(payload);
            }
            finalMethod = 'POST';
        }
        options.method = finalMethod;

        let response;
        try {
            response = await fetch(resolveUrl(url), options);
        } catch (networkError) {
            console.error('[ViciaAjax] erreur réseau', networkError);
            throw {
                success: false,
                message: networkError?.message || 'Erreur réseau lors de la requête.',
            };
        }
        const raw = await response.text();
        const normalized = raw ? raw.trim().replace(/^\uFEFF/, '') : '';
        let json;
        try {
            json = normalized ? JSON.parse(normalized) : null;
        } catch (e) {
            console.error('[ViciaAjax] réponse JSON invalide', { url: resolveUrl(url), raw, error: e });
            json = {
                success: false,
                message: raw ? 'Réponse invalide du serveur. Voir la console pour plus de détails.' : 'Réponse vide du serveur.',
                raw,
            };
        }

        if (!json || typeof json !== 'object') {
            console.error('[ViciaAjax] réponse JSON inattendue', { url: resolveUrl(url), raw, json });
            json = { success: false, message: 'Réponse vide du serveur.' };
        }

        if (!response.ok || json.success === false) {
            throw json;
        }

        return json;
    }

    return {
        get: (url) => request('GET', url),
        post: (url, data) => request('POST', url, data),
        put: (url, data) => request('PUT', url, data),
        del: (url, data) => request('DELETE', url, data),
    };
})();
