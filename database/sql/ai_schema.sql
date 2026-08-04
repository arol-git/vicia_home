-- =====================================================================
-- VICIA HOME AI — Extension additive du schéma existant
-- ------------------------------------------------------------------
-- Ce script NE MODIFIE AUCUNE TABLE EXISTANTE : il ajoute uniquement
-- les tables propres au module Vicia Home AI, avec des clés
-- étrangères vers `users` et `houses` (déjà en place). À exécuter
-- après database/sql/schema.sql, jamais à sa place.
-- =====================================================================

-- Sélectionnez votre base avant d'exécuter ce script, par exemple :
-- mysql -u vicia_user -p vicia_home2 < database/sql/ai_schema.sql

-- ---------------------------------------------------------------------
-- Table : ai_conversations
-- Une conversation par fil de discussion avec l'assistant. `house_id`
-- est nullable : une conversation peut démarrer avant toute sélection
-- de maison (ex. question générale). `pending_action` porte une
-- commande sensible en attente de confirmation explicite de
-- l'utilisateur (voir App\Services\ConversationMemory) — jamais
-- exécutée avant ce second tour de parole.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_conversations` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`          INT UNSIGNED    NOT NULL,
    `house_id`         INT UNSIGNED    NULL,
    `title`            VARCHAR(150)    NULL,
    `status`           ENUM('active','archived') NOT NULL DEFAULT 'active',
    `pending_action`   JSON            NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_ai_conversations_user` (`user_id`),
    KEY `idx_ai_conversations_house` (`house_id`),
    CONSTRAINT `fk_ai_conversations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ai_conversations_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : ai_messages
-- Historique complet des tours de parole d'une conversation.
-- `intent` porte le résultat de classification (App\Services\IntentClassifier)
-- pour les messages utilisateur — utile à l'audit et à l'amélioration
-- continue du classifieur.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_messages` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `conversation_id`  INT UNSIGNED    NOT NULL,
    `role`             ENUM('user','assistant','system') NOT NULL,
    `content`          TEXT            NOT NULL,
    `intent`           VARCHAR(50)     NULL COMMENT 'command, question, analysis, chitchat...',
    `metadata`         JSON            NULL COMMENT 'entités extraites, action exécutée, etc.',
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ai_messages_conversation` (`conversation_id`, `created_at`),
    CONSTRAINT `fk_ai_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `ai_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : ai_memory
-- Mémoire longue, par utilisateur, indépendante d'une conversation
-- précise (nom préféré, langue, préférences énoncées en langage
-- naturel) — consultée par App\Services\ConversationMemory pour éviter
-- de reposer une question déjà répondue par le passé.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_memory` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`          INT UNSIGNED    NOT NULL,
    `memory_key`       VARCHAR(100)    NOT NULL,
    `memory_value`     TEXT            NOT NULL,
    `updated_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ai_memory_user_key` (`user_id`, `memory_key`),
    CONSTRAINT `fk_ai_memory_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : ai_preferences
-- Préférences déclaratives de l'assistant pour un utilisateur (langue,
-- activation vocale...), distinctes de ai_memory (faits appris en
-- conversation) : ce sont des réglages explicites, modifiables depuis
-- l'interface du module.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_preferences` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`           INT UNSIGNED    NOT NULL,
    `language`          VARCHAR(10)     NOT NULL DEFAULT 'fr',
    `voice_enabled`     TINYINT(1)      NOT NULL DEFAULT 1,
    `speech_rate`       DECIMAL(3,2)    NOT NULL DEFAULT 1.00,
    `created_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ai_preferences_user` (`user_id`),
    CONSTRAINT `fk_ai_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : ai_logs
-- Journal d'audit des actions exécutées par l'assistant (toute
-- commande ayant abouti à un appel réel sur un équipement, un mode,
-- ou une donnée sensible), distinct du journal de conversation :
-- une conversation peut contenir beaucoup de messages sans aucune
-- action, cette table ne contient que les actions.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ai_logs` (
    `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`          INT UNSIGNED    NULL,
    `house_id`         INT UNSIGNED    NULL,
    `action`           VARCHAR(100)    NOT NULL COMMENT 'ex: equipment_toggle, mode_change, alarm_disarm',
    `detail`           TEXT            NULL,
    `status`           ENUM('success','error','denied') NOT NULL,
    `created_at`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ai_logs_user` (`user_id`),
    KEY `idx_ai_logs_house` (`house_id`),
    CONSTRAINT `fk_ai_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ai_logs_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
