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
│   ├── index.php             Point d'entrée de l'API REST qui permet 
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
CREATE DATABASE railway CHARACTER SET utf8mb4;
CREATE USER 'root'@'localhost' IDENTIFIED BY '';
GRANT ALL PRIVILEGES ON railway.* TO 'vicia_user'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql -u vicia_user -p railway < database/sql/schema.sql
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
Environment=MYSQL_HOST=127.0.0.1 
MYSQL_DATABASE=railway
MYSQL_USER=root 
MYSQL_PASSWORD=
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

Toute la configuration applicative se trouve dans `config/config.php` et `config/database.php`, avec surcharge possible par variables d'environnement (voir `.env.example`) : `APP_ENV`, `APP_URL`, `APP_KEY`, `MYSQL_HOST`, `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MQTT_HOST`, `MQTT_PORT`, `MQTT_USER`, `MQTT_PASS`.

Les paramètres modifiables depuis l'interface (nom du site, jeton du bot Telegram, identifiants SMTP) sont stockés en base dans la table `settings` et gérés depuis le module **Paramètres** (réservé aux administrateurs).

---

## 7. Comptes de démonstration

Le script `schema.sql` crée trois comptes de démonstration, tous avec le mot de passe **`ViciaHome@2026`** :

| Rôle           | E-mail                          |
|-----------------|----------------------------------|
| Administrateur  | admin@vicia-home.local          |
| Technicien      | technicien@vicia-home.local     |
| Utilisateur     | resident@vicia-home.local       |

> **Important** : changez ces mots de passe dès la mise en production, depuis le module **Profil** ou **Utilisateurs**.

### Rôles et permissions

| Action                                   | Utilisateur | Technicien | Administrateur |
|--------------------------------------------|:-----------:|:----------:|:---------------:|
| Consulter le tableau de bord                | ✅          | ✅         | ✅               |
| Piloter les équipements                     | ✅          | ✅         | ✅               |
| Créer/modifier pièces, équipements, capteurs | —          | ✅         | ✅               |
| Supprimer pièces, équipements, capteurs      | —          | —         | ✅               |
| Gérer les règles d'automatisation            | —          | ✅         | ✅               |
| Gérer les utilisateurs et les paramètres     | —          | —         | ✅               |

---

## 8. Le pont MQTT (`mqtt/subscriber.php`)

Ce démon CLI assure la liaison entre les modules ESP32 et la plateforme :

1. Il s'abonne aux topics `home/+/+/+` (télémétrie des capteurs), `home/security/#` et `home/network/#`.
2. Pour chaque message reçu correspondant à un capteur enregistré, il insère une mesure dans `sensor_readings` (déclenchant au passage le trigger SQL d'alerte de seuil).
3. Il évalue les règles actives du moteur d'automatisation concernées par ce capteur ou cet événement, et exécute les actions associées (commande d'équipement, notification Telegram/e-mail).

La classe `Mqtt\Publisher` est utilisée en sens inverse par les contrôleurs Web (`EquipmentController::toggle`) pour publier une commande vers un module ESP32 lorsqu'un utilisateur actionne un interrupteur depuis le tableau de bord.

> Le client MQTT (`mqtt/MqttClient.php`) est une implémentation minimaliste du protocole MQTT 3.1.1 sur socket brut, sans dépendance Composer, prenant en charge QoS 0. Pour un besoin de QoS 1/2 avec accusés de réception persistants, il est recommandé de migrer vers la bibliothèque `php-mqtt/client`, pleinement compatible avec la configuration de connexion existante (`mqtt/config.php`).

---

## 9. L'API REST

Point d'entrée : `/api/v1/...`. Toutes les réponses sont au format JSON.

### Authentification

```
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "admin@vicia-home.local", "password": "ViciaHome@2026" }
```

Réponse : `{ "success": true, "token": "...", "user": { ... } }`. Le jeton est ensuite transmis dans l'en-tête `Authorization: Bearer <token>` pour toutes les requêtes suivantes.

### Ressources disponibles

| Ressource       | Méthodes                                                   |
|------------------|--------------------------------------------------------------|
| `/rooms`          | `GET`, `GET /{id}`, `POST`, `PUT /{id}`, `DELETE /{id}`      |
| `/equipments`      | `GET`, `GET /{id}`, `POST`, `PUT /{id}`, `DELETE /{id}`, `POST /{id}/toggle` |
| `/sensors`          | `GET`, `GET /{id}`, `GET /{id}/history`, `POST`, `POST /{id}/readings`, `DELETE /{id}` |
| `/alerts`            | `GET`, `GET /{id}`, `POST /{id}/read`                       |
| `/automation`         | `GET`, `POST`, `POST /{id}/toggle`, `DELETE /{id}`          |

Exemple : basculer l'état d'un équipement

```bash
curl -X POST https://votre-domaine.example.com/api/v1/equipments/4/toggle \
  -H "Authorization: Bearer <token>"
```

---

## 10. Sécurité mise en œuvre

- **Mots de passe** : hachage BCrypt (`password_hash`/`password_verify`), jamais stockés ni journalisés en clair.
- **Sessions** : cookies `HttpOnly`, `SameSite=Lax`, régénération de l'identifiant de session à la connexion (prévention de la fixation de session).
- **CSRF** : jeton unique par session, vérifié par `hash_equals()` sur toute requête de modification (`App\Core\Csrf`).
- **XSS** : échappement systématique des sorties via la fonction `e()` dans toutes les vues.
- **Injections SQL** : 100 % des requêtes passent par des instructions préparées PDO (`App\Core\Database`), aucune concaténation de valeurs utilisateur dans une requête SQL.
- **Force brute** : blocage temporaire après 5 échecs de connexion en 15 minutes pour une même adresse IP (`LoginLog::recentFailures`).
- **Contrôle d'accès par rôle** : vérifié côté serveur dans chaque contrôleur (`Auth::requireRole()`), jamais uniquement côté interface.
- **Téléversements** : répertoire dédié hors de portée d'exécution directe de scripts (à sécuriser plus avant via la configuration Apache selon le type de fichiers acceptés).
- **En-têtes HTTP** : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` définis dans `public/.htaccess`.
- **Validation** : double validation, JavaScript (confort d'usage) et serveur (`App\Core\Validator`, seule véritable garantie de sécurité).

---

## 11. Modules fonctionnels

| Module              | Description                                                                 |
|----------------------|-------------------------------------------------------------------------------|
| Tableau de bord        | Indicateurs clés, graphiques Chart.js, activité récente, alertes             |
| Pièces                | Gestion des pièces de l'habitation (CRUD)                                    |
| Équipements            | LED, relais, ventilateur, pompe, servo, porte, fenêtre, sirène, caméra (CRUD + pilotage MQTT) |
| Capteurs                | PIR, DHT22, MQ-2, MQ-135, LDR, RFID, humidité du sol (CRUD + historique)     |
| Caméras                  | Vue de synthèse des équipements de type caméra                             |
| Réseau & cybersécurité    | Appareils détectés, listes blanche/noire, journal réseau                   |
| Consommation                | Estimation de la puissance active et de la consommation journalière     |
| Automatisation                | Moteur de règles « SI... ALORS... » configurable sans code             |
| Historique                      | Journal d'audit complet + connexions récentes                        |
| Alertes & notifications           | Centralisation des alertes système                                 |
| Utilisateurs                        | Gestion des comptes et rôles (administrateur uniquement)          |
| Paramètres                            | Configuration générale, Telegram, e-mail (administrateur uniquement) |
| Profil                                  | Informations personnelles et changement de mot de passe          |

---

## 12. Développement local rapide

Sans Apache, à des fins de test, le serveur de développement intégré de PHP peut être utilisé :

```bash
export MYSQL_HOST=127.0.0.1 MYSQL_DATABASE=railway MYSQL_USER=root MYSQL_PASSWORD=
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
- Jetons d'API à expiration (JWT signés) en remplacement du jeton statique actuel.
- Application mobile native consommant l'API REST existante.
- Intégration d'un flux vidéo temps réel pour le module Caméras (RTSP → HLS).
- Tests automatisés (PHPUnit) sous `tests/`.

---

## Licence et propriété

Projet développé pour le compte du bureau d'études Vicia Home. Tous droits réservés.
