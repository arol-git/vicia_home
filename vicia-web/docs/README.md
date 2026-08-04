# Vicia Home — Plateforme de gestion de maison intelligente

Plateforme Web complète de supervision et de pilotage d'une maison intelligente sécurisée, développée en PHP 8 (POO) / MySQL / HTML5 / CSS3 pur / JavaScript ES6, en architecture MVC maison, sans framework externe (ni Bootstrap, ni Laravel/Symfony/CodeIgniter).

Elle communique avec des modules ESP32 via MQTT (Mosquitto) pour piloter les équipements de la maison (éclairage, portail, arrosage, sirène...) et surveiller les capteurs (mouvement, température, humidité, gaz, luminosité, RFID). Elle intègre également un module de cybersécurité réseau (détection d'appareils inconnus, listes blanche/noire) et un moteur d'automatisation configurable sans écrire de code.

---

## 1. Sommaire

- [2. Stack technique](#2-stack-technique)
- [3. Architecture du projet](#3-architecture-du-projet)
- [4. Prérequis](#4-prérequis)
- [5. Installation](#5-installation)
- [6. Configuration](#6-configuration)
- [7. Comptes de démonstration](#7-comptes-de-démonstration)
- [8. Le pont MQTT (mqtt/subscriber.php)](#8-le-pont-mqtt-mqttsubscriberphp)
- [9. L'API REST](#9-lapi-rest)
- [10. Sécurité mise en œuvre](#10-sécurité-mise-en-œuvre)
- [11. Modules fonctionnels](#11-modules-fonctionnels)
- [12. Développement local rapide](#12-développement-local-rapide)
- [13. Dépannage](#13-dépannage)
- [14. Feuille de route](#14-feuille-de-route)

---

## 2. Stack technique

| Couche               | Technologie                                   |
|-----------------------|------------------------------------------------|
| Langage serveur       | PHP 8 (programmation orientée objet)           |
| Base de données       | MySQL 8 (InnoDB, clés étrangères, triggers)     |
| Frontal               | HTML5, CSS3 pur (Flexbox + CSS Grid), JavaScript ES6, AJAX (fetch API) |
| Graphiques            | Chart.js                                        |
| Icônes                | Font Awesome                                    |
| Messagerie objets connectés | MQTT (broker Mosquitto)                   |
| Serveur Web           | Apache 2 (mod_rewrite)                          |
| Système                | Linux Ubuntu (22.04/24.04 LTS recommandé)      |
| Gestion de version    | Git                                             |

Aucun framework PHP (Laravel, Symfony, CodeIgniter) ni framework CSS (Bootstrap, Tailwind) n'est utilisé : l'architecture MVC, le routeur, l'ORM minimal et le design system sont développés spécifiquement pour ce projet.

---

## 3. Architecture du projet

```
vicia-home/
├── app/
│   ├── controllers/      Contrôleurs MVC (un par module fonctionnel)
│   ├── models/            Modèles (accès aux données, un par entité)
│   ├── views/              Vues PHP (layout, pages par module)
│   ├── core/                Noyau du framework maison (Router, Model, Controller,
│   │                         Database, Auth, Session, Csrf, Request, Response, Validator)
│   └── helpers/            Fonctions utilitaires globales, Mailer, Notifier
├── config/
│   ├── config.php          Configuration générale de l'application
│   └── database.php        Paramètres de connexion MySQL
├── public/                  Racine Web (DocumentRoot Apache recommandé)
│   ├── index.php            Contrôleur frontal (point d'entrée unique)
│   ├── .htaccess            Réécriture d'URL et en-têtes de sécurité
│   ├── assets/
│   │   ├── css/              Feuilles de styles personnalisées (sans Bootstrap)
│   │   └── js/                Scripts JavaScript (AJAX, graphiques, interactions)
│   └── uploads/              Fichiers téléversés (avatars, etc.)
├── database/
│   └── sql/schema.sql        Script SQL complet (tables, clés, contraintes, triggers, jeux de données)
├── api/
│   ├── index.php             Point d'entrée de l'API REST
│   └── v1/                    Ressources de l'API (rooms, equipments, sensors, alerts, automation, auth)
├── mqtt/
│   ├── MqttClient.php        Client MQTT 3.1.1 minimaliste (sans dépendance externe)
│   ├── Publisher.php          Publication de commandes depuis l'interface Web
│   ├── subscriber.php         Démon d'écoute MQTT + moteur d'automatisation (à lancer en arrière-plan)
│   └── config.php             Configuration de connexion au broker
├── storage/logs/              Journaux applicatifs
├── tests/                      Emplacement des tests (voir §14 feuille de route)
└── docs/                        Documentation complémentaire
```

### Patron d'architecture

Le projet suit un patron **MVC (Modèle-Vue-Contrôleur)** classique :

- **`public/index.php`** (contrôleur frontal) reçoit toutes les requêtes HTTP, démarre la session, restaure l'authentification persistante puis délègue au `Router`.
- **`App\Core\Router`** résout l'URI demandée vers un couple `Contrôleur@méthode`, avec prise en charge des paramètres dynamiques (`/rooms/{id}`) et de la surcharge de méthode HTTP (`PUT`/`DELETE` envoyés en `POST` avec un champ `_method`).
- **Les contrôleurs** (`app/controllers`) orchestrent la logique métier : validation des entrées (`App\Core\Validator`), appels aux modèles, journalisation d'audit, puis réponse (vue HTML ou JSON selon le contexte AJAX).
- **Les modèles** (`app/models`) encapsulent l'accès aux données via des requêtes préparées PDO (`App\Core\Database`), prévenant toute injection SQL.
- **Les vues** (`app/views`) sont de simples fichiers PHP, avec échappement systématique des sorties via la fonction `e()` (protection XSS).

### Architecture multi-maisons (multi-tenant)

La plateforme héberge **plusieurs maisons**, chacune reliée à un ou plusieurs comptes utilisateurs :

- La table `houses` représente une habitation cliente (un foyer). Chaque maison possède un `slug` unique, utilisé comme segment d'espace de noms MQTT (`home/<slug>/...`) afin d'isoler les messages de chaque maison sur un même broker Mosquitto partagé.
- La table pivot `house_user` relie un utilisateur à une ou plusieurs maisons, avec un **rôle propre à chaque maison** : `owner` (propriétaire, tous droits), `resident` (usage courant, pilotage des équipements), `technician` (installation et maintenance). Un même utilisateur peut être `owner` d'une maison et `resident` d'une autre.
- `users.role` reste un rôle de **plateforme** (`admin` = support Vicia Home, accès à toutes les maisons ; `user` = client). Il ne détermine jamais les droits sur une maison précise — c'est `house_user.role_in_house` qui s'en charge.
- Toutes les ressources physiques (`rooms`, et par transitivité `equipments`, `sensors`), ainsi que `network_devices`, `automation_rules`, `alerts`, sont rattachées à une maison (`house_id`). Un modèle expose systématiquement une méthode `belongsToHouse()` vérifiée par le contrôleur avant toute lecture/écriture, empêchant qu'un utilisateur n'accède aux ressources d'une maison à laquelle il n'appartient pas — y compris en falsifiant un identifiant dans l'URL.
- La **maison actuellement sélectionnée** est mémorisée en session (`App\Core\Auth::currentHouseId()`) et modifiable à tout moment via le sélecteur de la barre latérale ou `POST /houses/switch/{id}`.
- `App\Core\Auth::requireHouseRole(array $roles)` remplace `requireRole()` dans tous les contrôleurs scopés à une maison : il vérifie le rôle de l'utilisateur sur la maison **actuellement sélectionnée**, et retourne son identifiant.
- L'API REST applique la même logique via le paramètre obligatoire `house_id` (query string en `GET`, corps JSON sinon), vérifié par `api_authorize_house()`.

---

## 4. Prérequis

- Ubuntu Server 22.04 LTS ou 24.04 LTS
- Apache 2.4+ avec `mod_rewrite` activé
- PHP 8.1+ avec les extensions : `pdo_mysql`, `mbstring`, `curl`, `json`
- MySQL 8.0+ (ou MariaDB 10.6+)
- Mosquitto (broker MQTT) 2.0+, avec TLS configuré pour la production
- Git

---

## 5. Installation

### 5.1 Récupération du projet

```bash
git clone <url-du-dépôt> vicia-home
cd vicia-home
```

### 5.2 Base de données

```bash
sudo mysql -u root -p <<'SQL'
CREATE DATABASE vicia_home CHARACTER SET utf8mb4;
CREATE USER 'vicia_user'@'localhost' IDENTIFIED BY 'un-mot-de-passe-robuste';
GRANT ALL PRIVILEGES ON vicia_home.* TO 'vicia_user'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql -u vicia_user -p vicia_home < database/sql/schema.sql
```

Le script `schema.sql` crée l'intégralité des tables, contraintes, index et triggers, et insère un jeu de données de démonstration (voir §7).

### 5.3 Configuration Apache

Définissez le `DocumentRoot` du site sur le dossier **`public/`** du projet (recommandé) :

```apache
<VirtualHost *:80>
    ServerName vicia-home.local
    DocumentRoot /var/www/vicia-home/public

    <Directory /var/www/vicia-home/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/vicia-home-error.log
    CustomLog ${APACHE_LOG_DIR}/vicia-home-access.log combined
</VirtualHost>
```

```bash
sudo a2enmod rewrite headers
sudo a2ensite vicia-home.conf
sudo systemctl reload apache2
```

> Si votre hébergement impose que le `DocumentRoot` pointe vers la racine du projet plutôt que vers `public/`, le fichier `.htaccess` à la racine redirige automatiquement les requêtes vers `public/` — mais la configuration ci-dessus reste la méthode recommandée en production.

### 5.4 Permissions

```bash
sudo chown -R www-data:www-data storage public/uploads
sudo chmod -R 775 storage public/uploads
```

### 5.5 Broker MQTT (Mosquitto)

```bash
sudo apt install mosquitto mosquitto-clients
sudo systemctl enable mosquitto
```

Configurez l'authentification et le TLS conformément aux exigences de sécurité du cahier des charges (certificats par module ESP32, ACL par topic). Un exemple minimal d'utilisateur applicatif :

```bash
sudo mosquitto_passwd -c /etc/mosquitto/passwd vicia_web
```

### 5.6 Démon d'écoute MQTT

Le fichier `mqtt/subscriber.php` doit tourner en permanence en arrière-plan pour enregistrer les mesures des capteurs et exécuter le moteur d'automatisation. Créez un service systemd :

```ini
# /etc/systemd/system/vicia-mqtt.service
[Unit]
Description=Vicia Home - Démon d'écoute MQTT
After=network.target mysql.service mosquitto.service

[Service]
Type=simple
User=www-data
Environment=DB_HOST=127.0.0.1 DB_NAME=vicia_home DB_USER=vicia_user DB_PASS=un-mot-de-passe-robuste
Environment=MQTT_HOST=127.0.0.1 MQTT_PORT=8883
ExecStart=/usr/bin/php /var/www/vicia-home/mqtt/subscriber.php
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now vicia-mqtt.service
sudo systemctl status vicia-mqtt.service
```

---

## 6. Configuration

Toute la configuration applicative se trouve dans `config/config.php` et `config/database.php`, avec surcharge possible par variables d'environnement (voir `.env.example`) : `APP_ENV`, `APP_URL`, `APP_KEY`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `MQTT_HOST`, `MQTT_PORT`, `MQTT_USER`, `MQTT_PASS`.

Les paramètres globaux modifiables depuis l'interface (nom du site, serveur SMTP partagé) sont stockés en base dans la table `settings` et gérés depuis le module **Paramètres** (réservé aux administrateurs de plateforme). Le jeton du bot Telegram et l'e-mail d'alerte sont, eux, propres à chaque maison et se configurent depuis **Mes maisons → modifier la maison**.

---

## 7. Comptes de démonstration

Le script `schema.sql` crée quatre comptes de démonstration répartis sur **deux maisons distinctes** (Villa Yaoundé et Résidence Douala), tous avec le mot de passe **`ViciaHome@2026`** :

| Compte                          | Rôle plateforme | Maison(s) et rôle associé                              |
|-----------------------------------|:----------------:|-----------------------------------------------------------|
| admin@vicia-home.local            | admin            | Support Vicia Home — accès à toutes les maisons          |
| arol@vicia-home.local             | user              | Propriétaire (`owner`) de la Villa Yaoundé                |
| technicien@vicia-home.local       | user              | Technicien sur la Villa Yaoundé **et** la Résidence Douala |
| resident@vicia-home.local         | user              | Propriétaire (`owner`) de la Résidence Douala              |

> **Important** : changez ces mots de passe dès la mise en production, depuis le module **Profil** ou **Utilisateurs**.

### Rôles et permissions

Deux niveaux de rôle coexistent : le **rôle de plateforme** (`users.role`), et le **rôle sur une maison précise** (`house_user.role_in_house`), ce dernier gouvernant l'essentiel des permissions au quotidien.

| Action                                        | Resident | Technician | Owner | Admin (plateforme) |
|--------------------------------------------------|:--------:|:----------:|:-----:|:--------------------:|
| Consulter le tableau de bord de la maison           | ✅       | ✅          | ✅     | ✅ (toutes maisons)    |
| Piloter les équipements                              | ✅       | ✅          | ✅     | ✅                     |
| Créer/modifier pièces, équipements, capteurs, règles  | —       | ✅          | ✅     | ✅                     |
| Supprimer pièces, équipements, capteurs, règles        | —       | —          | ✅     | ✅                     |
| Gérer les membres de la maison                          | —       | —          | ✅     | ✅                     |
| Créer une nouvelle maison                                | ✅ (devient owner de la maison créée) |
| Gérer les comptes utilisateurs et les paramètres globaux  | —       | —          | —     | ✅                     |

---

## 8. Le pont MQTT (`mqtt/subscriber.php`)

Ce démon CLI assure la liaison entre les modules ESP32 et la plateforme :

1. Il s'abonne aux topics `home/+/+/+/+` (télémétrie des capteurs, le deuxième segment étant le slug de la maison), `home/+/security/#` et `home/+/network/#`.
2. Pour chaque message reçu correspondant à un capteur enregistré, il vérifie que la carte ESP32 associée est bien appairée (voir §8bis), puis insère une mesure dans `sensor_readings` (déclenchant au passage le trigger SQL d'alerte de seuil).
3. Il évalue les règles actives du moteur d'automatisation concernées par ce capteur ou cet événement, et exécute les actions associées (commande d'équipement, notification Telegram/e-mail propre à la maison).

La classe `Mqtt\Publisher` est utilisée en sens inverse par les contrôleurs Web (`EquipmentController::toggle`) pour publier une commande vers un module ESP32 lorsqu'un utilisateur actionne un interrupteur depuis le tableau de bord.

> Le client MQTT (`mqtt/MqttClient.php`) est une implémentation minimaliste du protocole MQTT 3.1.1 sur socket brut, sans dépendance Composer, prenant en charge QoS 0. Pour un besoin de QoS 1/2 avec accusés de réception persistants, il est recommandé de migrer vers la bibliothèque `php-mqtt/client`, pleinement compatible avec la configuration de connexion existante (`mqtt/config.php`).

---

## 8bis. Appairage des cartes ESP32 (module Appareils)

Un topic MQTT nommé « home/villa-yaounde/lighting/salon/led1 » ne prouve à lui seul rien sur la carte qui l'émet : n'importe quel appareil mal configuré pourrait y publier. La plateforme résout ce problème par un **appairage explicite** :

1. Depuis le module **Appareils**, un propriétaire ou technicien enregistre l'identifiant matériel unique de la carte (`chip_id`, en pratique l'adresse MAC ou le chip ID Espressif) contre la maison actuellement sélectionnée (table `devices`, statut `paired`).
2. Chaque équipement ou capteur créé doit obligatoirement référencer une carte déjà appairée à **cette même maison** (`App\Models\Device::isPairedInHouse()`) ; le topic MQTT est alors **généré automatiquement** (`Device::generateTopic()`) à partir du slug de la maison, du type et de la zone indiqués — il n'est plus saisi à la main, ce qui élimine les fautes de frappe et les collisions entre maisons.
3. Le démon `mqtt/subscriber.php` vérifie, à chaque message de télémétrie, que la carte associée au capteur a bien le statut `paired` ; un message provenant d'une carte révoquée est ignoré et journalisé.
4. Révoquer une carte (`status = revoked`) l'empêche immédiatement d'être sélectionnée pour de nouveaux équipements/capteurs, sans supprimer ceux qui l'utilisaient déjà.

> Cette étape ne remplace pas la sécurisation transport (certificats X.509 par module, décrite au chapitre 9 du cahier des charges) : elle garantit l'intégrité du **modèle applicatif** (quelle carte contrôle quel équipement), le certificat MQTT garantissant l'authenticité de la **connexion réseau** elle-même.

---

## 9. L'API REST

Point d'entrée : `/api/v1/...`. Toutes les réponses sont au format JSON.

### Authentification (JWT)

```
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "admin@vicia-home.local", "password": "ViciaHome@2026" }
```

Réponse :
```json
{
  "success": true,
  "user": { "id": 1, "name": "...", "email": "...", "role": "admin" },
  "access_token": "eyJ...",
  "refresh_token": "56a2...",
  "token_type": "Bearer",
  "expires_in": 900
}
```

- `access_token` : JWT auto-porteur (`App\Core\Jwt`, HS256, signé avec `config('app_key')`), valable 15 minutes (`config('jwt_access_ttl')`). Transmis dans l'en-tête `Authorization: Bearer <access_token>` pour toutes les requêtes suivantes.
- `refresh_token` : chaîne opaque valable 30 jours (`config('jwt_refresh_ttl')`), à échanger contre une nouvelle paire de jetons via :

```
POST /api/v1/auth/refresh
Content-Type: application/json

{ "refresh_token": "56a2..." }
```

Chaque appel à `/auth/refresh` **fait tourner** le jeton de rafraîchissement : l'ancien devient immédiatement inutilisable. Le jeton de rafraîchissement de l'API est stocké (haché SHA-256) dans une colonne dédiée `users.api_refresh_token`, distincte de `users.remember_token` (session "Se souvenir de moi" de l'interface Web) — un même compte peut donc rester connecté simultanément sur le site et sur un client API (bot Telegram, etc.) sans que l'un ne déconnecte l'autre.

### Ressources disponibles

Toutes les ressources ci-dessous (à l'exception de `/auth` et `/houses` en lecture) exigent un paramètre **`house_id`** identifiant la maison ciblée — en query string pour `GET`, dans le corps JSON pour `POST`/`PUT`/`DELETE` — vérifié contre les droits de l'utilisateur sur cette maison (`api_authorize_house()`). Une requête sans accès à la maison demandée reçoit une réponse `403`.

| Ressource       | Méthodes                                                   |
|------------------|--------------------------------------------------------------|
| `/houses`          | `GET` (maisons de l'utilisateur), `PUT /{id}/mode` (confort/nuit/absence/urgence) |
| `/rooms`          | `GET`, `GET /{id}`, `POST`, `PUT /{id}`, `DELETE /{id}`      |
| `/equipments`      | `GET`, `GET /{id}`, `POST`, `PUT /{id}`, `DELETE /{id}`, `POST /{id}/toggle` |
| `/sensors`          | `GET`, `GET /{id}`, `GET /{id}/history`, `POST`, `POST /{id}/readings`, `DELETE /{id}` |
| `/devices`           | `GET` (cartes ESP32 appairées), `POST` (appairage), `POST /{id}/revoke` |
| `/network`            | `GET` (appareils détectés), `POST /{id}/whitelist`, `POST /{id}/blacklist` |
| `/consumption`         | `GET` (puissance active, estimation journalière, répartition par type) |
| `/alerts`            | `GET`, `GET /{id}`, `POST /{id}/read`                       |
| `/automation`         | `GET`, `POST`, `POST /{id}/toggle`, `DELETE /{id}`          |

Exemple : basculer l'état d'un équipement de la maison n°1

```bash
curl -X POST "https://votre-domaine.example.com/api/v1/equipments/4/toggle" \
  -H "Authorization: Bearer <access_token>" \
  -H "Content-Type: application/json" \
  -d '{"house_id": 1}'
```

---

## 10. Sécurité mise en œuvre

- **Mots de passe** : hachage BCrypt (`password_hash`/`password_verify`), jamais stockés ni journalisés en clair.
- **Sessions** : cookies `HttpOnly`, `SameSite=Lax`, régénération de l'identifiant de session à la connexion (prévention de la fixation de session).
- **CSRF** : jeton unique par session, vérifié par `hash_equals()` sur toute requête de modification (`App\Core\Csrf`).
- **XSS** : échappement systématique des sorties via la fonction `e()` dans toutes les vues.
- **Injections SQL** : 100 % des requêtes passent par des instructions préparées PDO (`App\Core\Database`), aucune concaténation de valeurs utilisateur dans une requête SQL.
- **Force brute** : blocage temporaire après 5 échecs de connexion en 15 minutes pour une même adresse IP (`LoginLog::recentFailures`).
- **Isolation multi-maisons** : chaque contrôleur scopé vérifie explicitement (`belongsToHouse()`) qu'une ressource ciblée appartient bien à la maison actuellement autorisée, empêchant qu'un utilisateur n'accède aux données d'une maison à laquelle il n'appartient pas, y compris en falsifiant un identifiant dans l'URL ou le corps de la requête.
- **Contrôle d'accès par rôle** : vérifié côté serveur dans chaque contrôleur (`Auth::requireHouseRole()` pour les ressources d'une maison, `Auth::requireRole()` pour les actions de plateforme), jamais uniquement côté interface.
- **Téléversements** : répertoire dédié hors de portée d'exécution directe de scripts (à sécuriser plus avant via la configuration Apache selon le type de fichiers acceptés).
- **En-têtes HTTP** : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` définis dans `public/.htaccess`.
- **Validation** : double validation, JavaScript (confort d'usage) et serveur (`App\Core\Validator`, seule véritable garantie de sécurité).

---

## 11. Modules fonctionnels

| Module              | Description                                                                 |
|----------------------|-------------------------------------------------------------------------------|
| Mes maisons            | Création de maisons, sélection de la maison courante, gestion des membres et de leur rôle |
| Appareils (ESP32)        | Appairage des cartes physiques à la maison (voir §8bis)                    |
| Tableau de bord        | Indicateurs clés, graphiques Chart.js, activité récente, alertes — de la maison sélectionnée |
| Pièces                | Gestion des pièces de la maison sélectionnée (CRUD)                          |
| Équipements            | LED, relais, ventilateur, pompe, servo, porte, fenêtre, sirène, caméra (CRUD + pilotage MQTT) |
| Capteurs                | PIR, DHT22, MQ-2, MQ-135, LDR, RFID, humidité du sol (CRUD + historique)     |
| Caméras                  | Vue de synthèse des équipements de type caméra                             |
| Réseau & cybersécurité    | Appareils détectés, listes blanche/noire, journal réseau — propres à la maison |
| Consommation                | Estimation de la puissance active et de la consommation journalière     |
| Automatisation                | Moteur de règles « SI... ALORS... » configurable sans code, propre à la maison |
| Historique                      | Journal d'audit de la maison + connexions récentes du compte         |
| Alertes & notifications           | Centralisation des alertes système de la maison                    |
| Utilisateurs                        | Gestion des comptes et rôles de plateforme (administrateur uniquement) |
| Paramètres                            | Configuration globale de plateforme (administrateur uniquement)   |
| Profil                                  | Informations personnelles et changement de mot de passe          |

---

## 12. Développement local rapide

Sans Apache, à des fins de test, le serveur de développement intégré de PHP peut être utilisé :

```bash
export DB_HOST=127.0.0.1 DB_NAME=vicia_home DB_USER=vicia_user DB_PASS=un-mot-de-passe-robuste
cd public
php -S 127.0.0.1:8000 index.php
```

Puis rendez-vous sur `http://127.0.0.1:8000/login`.

Pour tester l'API isolément :

```bash
cd vicia-home
php -S 127.0.0.1:8001 api/index.php
# puis requêtes sur http://127.0.0.1:8001/api/v1/...
```

---

## 13. Dépannage

| Symptôme                                              | Piste de résolution                                                        |
|---------------------------------------------------------|-------------------------------------------------------------------------------|
| Erreur 500 « la connexion à la base de données a échoué » | Vérifier les identifiants dans `config/database.php` / variables d'environnement, et l'extension `pdo_mysql` |
| Page blanche                                              | Passer `APP_ENV=development` pour afficher les erreurs PHP détaillées      |
| Commandes d'équipement sans effet côté ESP32               | Vérifier que le broker Mosquitto accepte les connexions et que `mqtt/subscriber.php` tourne (`systemctl status vicia-mqtt`) |
| Jeton CSRF invalide (419)                                    | Recharger la page (jeton régénéré par session) ; vérifier l'horloge serveur |
| Table des matières ou listes vides                            | Exécuter `database/sql/schema.sql` intégralement, y compris les triggers |

---

## 14. Feuille de route

- Migration progressive de `mqtt/MqttClient.php` vers `php-mqtt/client` pour la prise en charge QoS 1/2.
- Application mobile native consommant l'API REST existante.
- Intégration d'un flux vidéo temps réel pour le module Caméras (RTSP → HLS).
- Tests automatisés (PHPUnit) sous `tests/`.

---

## Licence et propriété

Projet développé pour le compte du bureau d'études Vicia Home. Tous droits réservés.
