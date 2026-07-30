# Vicia Home — Bot Telegram

Bot Telegram officiel de la plateforme Vicia Home. Ne touche jamais
`vicia_home` directement : toutes les opérations passent par l'API
REST (`/api/v1/...`). Voir `Vicia_Bot_Analyse_Prealable.md` pour
l'architecture complète et le détail des 17 modules livrés.

## 1. Prérequis serveur

- PHP 8.1+ (`pdo_mysql`, `mbstring`, `curl`, `openssl`, `json`)
- MySQL 8.0+ (base `vicia_bot`, séparée de `vicia_home`)
- Composer
- `wkhtmltopdf` (génération des rapports PDF — `apt install wkhtmltopdf`)
- Un nom de domaine HTTPS valide (Telegram exige HTTPS pour les webhooks)

## 2. Installation

```bash
git clone <dépôt> vicia-bot && cd vicia-bot
composer install --no-dev --optimize-autoloader
cp .env.example .env   # puis renseigner toutes les valeurs
```

```bash
mysql -u root -p <<'SQL'
CREATE DATABASE vicia_bot CHARACTER SET utf8mb4;
CREATE USER 'vicia_bot_user'@'localhost' IDENTIFIED BY 'un-mot-de-passe-robuste';
GRANT ALL PRIVILEGES ON vicia_bot.* TO 'vicia_bot_user'@'localhost';
SQL
mysql -u vicia_bot_user -p vicia_bot < database/sql/schema.sql
```

```bash
sudo chown -R www-data:www-data logs storage cache uploads
```

## 3. Configuration Apache

Le bot expose **deux** points d'entrée HTTPS distincts, tous deux sous `public/` :

```apache
<VirtualHost *:443>
    ServerName bot.vicia-home.example.com
    DocumentRoot /var/www/vicia-bot/public
    <Directory /var/www/vicia-bot/public>
        AllowOverride None
        Require all granted
    </Directory>
    # ... certificats SSL ...
</VirtualHost>
```

- `https://bot.vicia-home.example.com/index.php` — webhook Telegram
- `https://bot.vicia-home.example.com/webhook-alert.php` — notifications entrantes depuis Vicia Home

## 4. Enregistrement du webhook Telegram

```bash
curl -F "url=https://bot.vicia-home.example.com/index.php" \
     -F "secret_token=<valeur de TELEGRAM_WEBHOOK_SECRET>" \
     "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook"
```

## 5. Connexion des notifications poussées (à faire côté `vicia-home`)

Le module 15 attend que la plateforme appelle `webhook-alert.php` après
toute alerte notifiable. Ajouter dans **`mqtt/subscriber.php`** (projet
`vicia-home`), juste après tout `Alert::create([...])` jugé critique :

```php
function notifyBot(int $houseId, array $alert): void
{
    $body = json_encode(['house_id' => $houseId, 'alert' => $alert]);
    $secret = getenv('VICIA_ALERT_WEBHOOK_SECRET'); // même valeur que côté bot
    $signature = hash_hmac('sha256', $body, $secret);

    $ch = curl_init('https://bot.vicia-home.example.com/webhook-alert.php');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', "X-Vicia-Signature: $signature"],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_RETURNTRANSFER => true,
    ]);
    curl_exec($ch);
    curl_close($ch);
}
```

`alert` doit contenir au minimum `id`, `severity`, `message` (soit
exactement les colonnes de la table `alerts` récemment insérées).

## 6. Tâche planifiée (purge)

```
0 * * * * php /var/www/vicia-bot/bin/purge.php >> /var/www/vicia-bot/logs/purge.log 2>&1
```

## 7. Vérification

Vérification locale sans appeler Telegram :

```bash
php bin/check.php
```

Le script contrôle la présence des variables `.env`, l'autoload
Composer et les tables de la base `vicia_bot`. Les jetons et secrets
sont masqués dans la sortie.

```
Telegram → /statut   → "✅ Vicia Home Bot — opérationnel"
Telegram → /start     → procédure de liaison de compte
```

## 8. Modules livrés

| # | Module | Statut |
|---|---|---|
| 0 | Prérequis plateforme (endpoints API + JWT) | ✅ |
| 1 | Socle projet (Composer, config, arborescence) | ✅ |
| 2 | Noyau (Router, Controller, Exceptions, Logger, ErrorHandler) | ✅ |
| 3 | Base de données du bot (`vicia_bot`) | ✅ |
| 4 | Sécurité (whitelist, rate limit, anti-rejeu, validation) | ✅ |
| 5 | Client API Vicia Home (JWT, refresh auto) | ✅ |
| 6 | Liaison de compte & session, sélection de maison | ✅ |
| 7 | Menu principal & clavier | ✅ |
| 8 | Maison / Équipements / Portes / Éclairage | ✅ |
| 9 | Capteurs (température, humidité) | ✅ |
| 10 | Caméras | ✅ |
| 11 | Alarme & modes | ✅ |
| 12 | Réseau & cybersécurité | ✅ |
| 13 | Automatisation (liste + bascule ; création réservée à l'interface Web) | ✅ |
| 14 | Historique & rapports PDF | ✅ |
| 15 | Notifications poussées | ✅ |
| 16 | Paramètres / 2FA (architecture prête, activation différée) | ✅ |
| 17 | Déploiement | ✅ |

## 9. Limites connues

- Dans cet environnement XAMPP, les dépendances de production ont été
  installées avec `composer install --no-dev --optimize-autoloader
  --ignore-platform-reqs`. L'installation complète avec les dépendances
  de test demande les extensions PHP XML (`ext-dom`, `ext-xml`).
- La création de nouvelles règles d'automatisation depuis le bot n'est
  pas implémentée (lecture/bascule seulement) — extension naturelle du
  Module 13 si besoin.
- L'authentification à deux facteurs a son architecture prête
  (colonne dédiée) mais n'est pas activée.
