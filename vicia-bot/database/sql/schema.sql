-- =====================================================================
-- VICIA HOME — Bot Telegram
-- Script de création de la base de données PROPRE AU BOT (`vicia_bot`)
-- SGBD cible : MySQL 8.0+
--
-- Cette base ne contient AUCUNE donnée métier de la plateforme (pas
-- de maisons, pièces, équipements, capteurs...) : celles-ci restent
-- exclusivement dans `vicia_home`, accessible uniquement via l'API
-- REST (voir Bot\Services\ViciaApiClient, module suivant). Les
-- colonnes ci-dessous qui référencent un identifiant de cette autre
-- base (vicia_user_id, current_house_id, house_id, alert_id) sont de
-- simples entiers, SANS contrainte de clé étrangère inter-base — ce
-- que MySQL ne permet d'ailleurs pas nativement entre deux bases.
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS `vicia_bot`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `vicia_bot`;

-- ---------------------------------------------------------------------
-- Table : telegram_users
-- Liaison entre un compte Telegram et un compte Vicia Home. Une ligne
-- par utilisateur Telegram ayant complété la procédure de liaison
-- (UC-01). Les jetons d'accès/rafraîchissement de l'API Vicia Home
-- sont stockés CHIFFRÉS (voir Bot\Services\TokenVault, module
-- suivant) — jamais en clair, jamais journalisés.
-- ---------------------------------------------------------------------
CREATE TABLE `telegram_users` (
    `id`                        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `telegram_id`               BIGINT UNSIGNED NOT NULL COMMENT 'identifiant utilisateur Telegram',
    `telegram_username`         VARCHAR(100)    NULL,
    `vicia_user_id`             INT UNSIGNED    NOT NULL COMMENT 'référence vers users.id de la base vicia_home',
    `access_token_encrypted`    TEXT            NULL,
    `refresh_token_encrypted`   TEXT            NULL,
    `token_expires_at`          DATETIME        NULL,
    `current_house_id`          INT UNSIGNED    NULL COMMENT 'référence vers houses.id de la base vicia_home',
    `two_factor_secret`         VARCHAR(255)    NULL COMMENT 'architecture prête — activation différée (voir analyse préalable)',
    `linked_at`                 DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at`                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_telegram_users_telegram_id` (`telegram_id`),
    KEY `idx_telegram_users_vicia_user` (`vicia_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : bot_sessions
-- État de conversation en cours pour un utilisateur Telegram (étape
-- de liaison de compte en attente de saisie, sélection de maison,
-- formulaire conversationnel type "seuil d'alerte à modifier"...). Le
-- bot n'a pas d'état en mémoire entre deux updates : tout transite
-- par cette table. `payload` porte les données saisies aux étapes
-- précédentes d'un même flux (ex. l'e-mail déjà saisi, en attendant
-- le mot de passe).
-- ---------------------------------------------------------------------
CREATE TABLE `bot_sessions` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `telegram_id`   BIGINT UNSIGNED NOT NULL,
    `state`         VARCHAR(50)     NOT NULL COMMENT 'ex. awaiting_email, awaiting_password, awaiting_threshold_value',
    `payload`       JSON            NULL,
    `expires_at`    DATETIME        NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_bot_sessions_telegram_id` (`telegram_id`),
    KEY `idx_bot_sessions_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : access_list
-- Liste blanche/noire des utilisateurs Telegram autorisés à
-- interagir avec le bot (UC-15), vérifiée par
-- Bot\Middlewares\WhitelistMiddleware avant tout traitement.
-- ---------------------------------------------------------------------
CREATE TABLE `access_list` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `telegram_id`   BIGINT UNSIGNED NOT NULL,
    `type`          ENUM('whitelist','blacklist') NOT NULL,
    `reason`        VARCHAR(255)    NULL,
    `created_by`    INT UNSIGNED    NULL COMMENT 'référence vers users.id (vicia_home) de l’administrateur à l’origine de l’action',
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_access_list_telegram_id` (`telegram_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : notification_log
-- Traçabilité des notifications poussées envoyées (UC-14) : évite
-- qu'une même alerte soit renvoyée deux fois au même utilisateur en
-- cas de nouvelle tentative côté plateforme (idempotence), et sert de
-- journal d'audit ("qui a été notifié de quoi, et quand").
-- ---------------------------------------------------------------------
CREATE TABLE `notification_log` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `house_id`      INT UNSIGNED    NOT NULL COMMENT 'référence vers houses.id (vicia_home)',
    `alert_id`      INT UNSIGNED    NULL COMMENT 'référence vers alerts.id (vicia_home), NULL pour une notification non liée à une alerte (ex. rapport à la demande)',
    `telegram_id`   BIGINT UNSIGNED NOT NULL,
    `status`        ENUM('sent','failed') NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_notification_log_alert_user` (`alert_id`, `telegram_id`),
    KEY `idx_notification_log_house` (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : rate_limit_hits
-- Fenêtre glissante de requêtes par utilisateur Telegram, consultée
-- par Bot\Middlewares\RateLimitMiddleware. Purge périodique
-- recommandée (voir docs/README.md) des lignes de plus de quelques
-- heures : cette table n'a pas vocation à devenir un historique.
-- ---------------------------------------------------------------------
CREATE TABLE `rate_limit_hits` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `telegram_id`   BIGINT UNSIGNED NOT NULL,
    `hit_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_rate_limit_telegram_time` (`telegram_id`, `hit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : processed_updates
-- Anti-rejeu (replay attack) : chaque `update_id` Telegram est unique
-- et croissant. Bot\Middlewares\ReplayProtectionMiddleware tente d'y
-- insérer l'update_id avant tout traitement ; un échec d'insertion
-- (clé déjà présente) signale un rejeu et interrompt le traitement.
-- ---------------------------------------------------------------------
CREATE TABLE `processed_updates` (
    `update_id`     BIGINT UNSIGNED PRIMARY KEY,
    `processed_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : security_events
-- Journal d'audit des refus et incidents de sécurité (liste noire,
-- limitation de débit dépassée, rejeu détecté, échec de vérification
-- du secret webhook...), distinct du journal applicatif général
-- (voir Bot\Core\Logger, canal "security", qui écrit en fichier —
-- cette table permet en complément des requêtes structurées, par
-- exemple pour un futur tableau de bord d'administration).
-- ---------------------------------------------------------------------
CREATE TABLE `security_events` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `telegram_id`   BIGINT UNSIGNED NULL,
    `event_type`    VARCHAR(50)     NOT NULL COMMENT 'whitelist_denied, blacklist_denied, rate_limited, replay_detected, invalid_webhook_secret, unauthorized_action',
    `description`   VARCHAR(255)    NULL,
    `ip_address`    VARCHAR(45)     NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_security_events_telegram` (`telegram_id`),
    KEY `idx_security_events_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
