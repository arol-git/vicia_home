-- =====================================================================
-- VICIA HOME — Plateforme de gestion de maison intelligente
-- Script de création de la base de données
-- SGBD cible : MySQL 8.0+
--
-- ARCHITECTURE MULTI-MAISONS (multi-tenant)
-- ------------------------------------------------------------------
-- La plateforme héberge plusieurs maisons (`houses`), chacune reliée
-- à un ou plusieurs comptes utilisateurs via la table pivot
-- `house_user`, avec un rôle propre à chaque maison
-- (owner / resident / technician). Toutes les ressources physiques
-- (pièces, équipements, capteurs, appareils réseau, règles
-- d'automatisation, alertes) sont rattachées à une maison précise,
-- directement ou par transitivité via `rooms.house_id`.
--
-- `users.role` reste un rôle de PLATEFORME (admin = équipe Vicia Home
-- avec accès à toutes les maisons à des fins de support ; user =
-- client final). Les droits d'action sur une maison donnée sont
-- gouvernés par `house_user.role_in_house`, vérifié par
-- App\Core\Auth::requireHouseRole() côté application.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `vicia_home2`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `vicia_home2`;

-- ---------------------------------------------------------------------
-- Table : users
-- Comptes utilisateurs de la plateforme. `role` est un rôle de
-- PLATEFORME (admin = support Vicia Home, user = client). Les droits
-- sur une maison précise sont définis dans `house_user`.
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`              VARCHAR(100)        NOT NULL,
    `email`             VARCHAR(150)        NOT NULL,
    `password_hash`     VARCHAR(255)        NOT NULL,
    `role`              ENUM('admin','user','technicien') NOT NULL DEFAULT 'user' COMMENT 'rôle de plateforme (admin = support Vicia Home)',
    `avatar`            VARCHAR(255)        NULL,
    `phone`             VARCHAR(30)         NULL,
    `notification_email` VARCHAR(150)       NULL,
    `telegram_name`      VARCHAR(100)       NULL,
    `remember_token`    VARCHAR(100)        NULL,
    `status`            ENUM('active','suspended') NOT NULL DEFAULT 'active',
    `last_login_at`     DATETIME            NULL,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : password_resets
-- ---------------------------------------------------------------------
CREATE TABLE `password_resets` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `email`         VARCHAR(150)    NOT NULL,
    `token`         VARCHAR(100)    NOT NULL,
    `expires_at`    DATETIME        NOT NULL,
    `used`          TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_password_resets_token` (`token`),
    KEY `idx_password_resets_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : login_logs
-- ---------------------------------------------------------------------
CREATE TABLE `login_logs` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT UNSIGNED    NULL,
    `email_used`    VARCHAR(150)    NOT NULL,
    `ip_address`    VARCHAR(45)     NOT NULL,
    `user_agent`    VARCHAR(255)    NULL,
    `status`        ENUM('success','failed') NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_login_logs_user` (`user_id`),
    CONSTRAINT `fk_login_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : houses
-- Une maison = une habitation cliente sur la plateforme (un foyer).
-- `slug` sert de segment d'espace de noms MQTT (ex. topic
-- home/<slug>/security/salon/pir), garantissant l'isolation des
-- messages entre maisons sur un même broker partagé.
-- ---------------------------------------------------------------------
CREATE TABLE `houses` (
    `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`                  VARCHAR(150)    NOT NULL,
    `slug`                  VARCHAR(60)     NOT NULL COMMENT 'segment d’espace de noms MQTT, ex. villa-yaounde',
    `address`               VARCHAR(255)    NULL,
    `city`                  VARCHAR(100)    NULL,
    `timezone`              VARCHAR(50)     NOT NULL DEFAULT 'Africa/Douala',
    `telegram_bot_token`    VARCHAR(150)    NULL,
    `telegram_chat_id`      VARCHAR(100)    NULL,
    `alert_email`           VARCHAR(150)    NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_houses_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : house_user (table pivot — relation many-to-many)
-- Associe un utilisateur à une ou plusieurs maisons, avec un rôle
-- PROPRE À CHAQUE MAISON : owner (propriétaire, tous droits sur la
-- maison), resident (usage courant), technician (installation et
-- maintenance). Un même utilisateur peut être owner d'une maison et
-- resident d'une autre.
-- ---------------------------------------------------------------------
CREATE TABLE `house_user` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `house_id`       INT UNSIGNED    NOT NULL,
    `user_id`        INT UNSIGNED    NOT NULL,
    `role_in_house`  ENUM('owner','resident','technician') NOT NULL DEFAULT 'resident',
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_house_user` (`house_id`, `user_id`),
    KEY `idx_house_user_user` (`user_id`),
    CONSTRAINT `fk_house_user_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_house_user_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : rooms
-- Pièces d'une maison. Rattachées obligatoirement à une maison :
-- c'est ce rattachement qui scope, par transitivité, les équipements
-- et capteurs qu'elles contiennent.
-- ---------------------------------------------------------------------
CREATE TABLE `rooms` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `house_id`      INT UNSIGNED    NOT NULL,
    `name`          VARCHAR(100)    NOT NULL,
    `type`          ENUM('salon','cuisine','chambre','garage','bureau','salle_de_bain','jardin','terrasse','autre') NOT NULL DEFAULT 'autre',
    `floor`         VARCHAR(50)     NULL,
    `icon`          VARCHAR(50)     NOT NULL DEFAULT 'fa-door-open',
    `description`   VARCHAR(255)    NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_rooms_house` (`house_id`),
    CONSTRAINT `fk_rooms_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : equipments
-- ---------------------------------------------------------------------
CREATE TABLE `equipments` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id`       INT UNSIGNED    NOT NULL,
    `name`          VARCHAR(100)    NOT NULL,
    `type`          ENUM('led','relais','ventilateur','pompe','servo','porte','fenetre','sirene','camera') NOT NULL,
    `icon`          VARCHAR(50)     NOT NULL DEFAULT 'fa-lightbulb',
    `state`         TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '0 = éteint/fermé, 1 = allumé/ouvert',
    `mqtt_topic`    VARCHAR(150)    NOT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `last_state_change` DATETIME    NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_equipments_topic` (`mqtt_topic`),
    KEY `idx_equipments_room` (`room_id`),
    CONSTRAINT `fk_equipments_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : sensors
-- ---------------------------------------------------------------------
CREATE TABLE `sensors` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id`       INT UNSIGNED    NOT NULL,
    `name`          VARCHAR(100)    NOT NULL,
    `type`          ENUM('pir','dht22_temp','dht22_hum','mq2','mq135','ldr','rfid','humidite_sol') NOT NULL,
    `unit`          VARCHAR(20)     NOT NULL DEFAULT '',
    `icon`          VARCHAR(50)     NOT NULL DEFAULT 'fa-microchip',
    `mqtt_topic`    VARCHAR(150)    NOT NULL,
    `alert_threshold` DECIMAL(10,2) NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_sensors_topic` (`mqtt_topic`),
    KEY `idx_sensors_room` (`room_id`),
    CONSTRAINT `fk_sensors_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : sensor_readings
-- ---------------------------------------------------------------------
CREATE TABLE `sensor_readings` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sensor_id`     INT UNSIGNED    NOT NULL,
    `value`         DECIMAL(10,2)   NOT NULL,
    `recorded_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_readings_sensor_time` (`sensor_id`, `recorded_at`),
    CONSTRAINT `fk_readings_sensor` FOREIGN KEY (`sensor_id`) REFERENCES `sensors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : alerts
-- Rattachée à une maison (nullable uniquement pour d'éventuelles
-- alertes systèmes transverses à toute la plateforme).
-- ---------------------------------------------------------------------
CREATE TABLE `alerts` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `house_id`      INT UNSIGNED    NULL,
    `type`          VARCHAR(50)     NOT NULL COMMENT 'intrusion, reseau, capteur, systeme',
    `severity`      ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    `source`        VARCHAR(100)    NULL,
    `message`       VARCHAR(255)    NOT NULL,
    `is_read`       TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_alerts_house` (`house_id`),
    KEY `idx_alerts_read` (`is_read`),
    KEY `idx_alerts_severity` (`severity`),
    CONSTRAINT `fk_alerts_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `devices` (
    `id`                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `house_id`               INT UNSIGNED NOT NULL,
    `chip_id`                VARCHAR(50)  NOT NULL COMMENT 'identifiant matériel unique de l’ESP32 (MAC ou chip ID)',
    `label`                  VARCHAR(100) NOT NULL,
    `certificate_fingerprint` VARCHAR(150) NULL,
    `firmware_version`       VARCHAR(30)  NULL,
    `status`                 ENUM('pending','paired','revoked') NOT NULL DEFAULT 'pending',
    `last_seen`              DATETIME     NULL,
    `created_at`             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_devices_chip` (`chip_id`),
    CONSTRAINT `fk_devices_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `equipments` ADD COLUMN `device_id` INT UNSIGNED NULL AFTER `room_id`,
    ADD CONSTRAINT `fk_equipments_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL;

ALTER TABLE `sensors` ADD COLUMN `device_id` INT UNSIGNED NULL AFTER `room_id`,
    ADD CONSTRAINT `fk_sensors_device` FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL;

-- ---------------------------------------------------------------------
-- Table : network_devices
-- Appareils détectés sur le réseau d'UNE maison précise (chaque
-- maison a son propre réseau domestique et son propre VLAN IoT).
-- ---------------------------------------------------------------------
CREATE TABLE `network_devices` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `house_id`      INT UNSIGNED    NOT NULL,
    `mac_address`   VARCHAR(17)     NOT NULL,
    `ip_address`    VARCHAR(45)     NULL,
    `hostname`      VARCHAR(150)    NULL,
    `vendor`        VARCHAR(150)    NULL,
    `list_status`   ENUM('unknown','whitelisted','blacklisted') NOT NULL DEFAULT 'unknown',
    `first_seen`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_blocked`    TINYINT(1)      NOT NULL DEFAULT 0,
    UNIQUE KEY `uq_network_devices_house_mac` (`house_id`, `mac_address`),
    CONSTRAINT `fk_network_devices_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : network_logs
-- ---------------------------------------------------------------------
CREATE TABLE `network_logs` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `device_id`     INT UNSIGNED    NULL,
    `event_type`    VARCHAR(60)     NOT NULL,
    `description`   VARCHAR(255)    NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_network_logs_device` (`device_id`),
    CONSTRAINT `fk_network_logs_device` FOREIGN KEY (`device_id`) REFERENCES `network_devices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : automation_rules
-- Rattachée à une maison : les conditions et actions d'une règle
-- portent toujours sur des capteurs/équipements de CETTE maison.
-- ---------------------------------------------------------------------
CREATE TABLE `automation_rules` (
    `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `house_id`              INT UNSIGNED    NOT NULL,
    `name`                  VARCHAR(150)    NOT NULL,
    `condition_source`      ENUM('sensor','event','time') NOT NULL DEFAULT 'sensor',
    `condition_sensor_id`   INT UNSIGNED    NULL,
    `condition_operator`    ENUM('>','<','>=','<=','=','!=') NULL,
    `condition_value`       VARCHAR(50)     NULL,
    `condition_event`       VARCHAR(50)     NULL,
    `action_equipment_id`   INT UNSIGNED    NULL,
    `action_state`          TINYINT(1)      NULL,
    `notify_telegram`       TINYINT(1)      NOT NULL DEFAULT 0,
    `notify_email`          TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`             TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`            INT UNSIGNED    NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_rules_house` (`house_id`),
    KEY `idx_rules_sensor` (`condition_sensor_id`),
    KEY `idx_rules_equipment` (`action_equipment_id`),
    CONSTRAINT `fk_rules_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rules_sensor` FOREIGN KEY (`condition_sensor_id`) REFERENCES `sensors` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rules_equipment` FOREIGN KEY (`action_equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rules_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : automation_logs
-- ---------------------------------------------------------------------
CREATE TABLE `automation_logs` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `rule_id`       INT UNSIGNED    NOT NULL,
    `triggered_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `result`        VARCHAR(255)    NULL,
    KEY `idx_automation_logs_rule` (`rule_id`),
    CONSTRAINT `fk_automation_logs_rule` FOREIGN KEY (`rule_id`) REFERENCES `automation_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : activity_logs
-- `house_id` est nullable : certaines actions (connexion, modification
-- du profil) ne se rattachent à aucune maison en particulier.
-- ---------------------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT UNSIGNED    NULL,
    `house_id`      INT UNSIGNED    NULL,
    `action`        VARCHAR(100)    NOT NULL,
    `description`   VARCHAR(255)    NULL,
    `ip_address`    VARCHAR(45)     NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_activity_logs_user` (`user_id`),
    KEY `idx_activity_logs_house` (`house_id`),
    CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_activity_logs_house` FOREIGN KEY (`house_id`) REFERENCES `houses` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : mqtt_logs
-- ---------------------------------------------------------------------
CREATE TABLE `mqtt_logs` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `topic`         VARCHAR(150)    NOT NULL,
    `payload`       TEXT            NULL,
    `direction`     ENUM('in','out') NOT NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_mqtt_logs_topic` (`topic`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : settings
-- Paramètres GLOBAUX de la plateforme uniquement (identité du site,
-- thème par défaut). Les paramètres propres à une maison — jeton
-- Telegram, e-mail d'alerte — vivent directement sur `houses`.
-- ---------------------------------------------------------------------
CREATE TABLE `settings` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key`   VARCHAR(100)    NOT NULL,
    `setting_value` TEXT            NULL,
    UNIQUE KEY `uq_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- TRIGGERS
-- =====================================================================

DELIMITER $$

CREATE TRIGGER `trg_equipments_before_update`
BEFORE UPDATE ON `equipments`
FOR EACH ROW
BEGIN
    IF NEW.state <> OLD.state THEN
        SET NEW.last_state_change = NOW();
    END IF;
END$$

-- Détection automatique d'un appareil inconnu : l'alerte générée
-- hérite désormais de la maison de l'appareil détecté.
CREATE TRIGGER `trg_network_devices_after_insert`
AFTER INSERT ON `network_devices`
FOR EACH ROW
BEGIN
    IF NEW.list_status = 'unknown' THEN
        INSERT INTO `alerts` (`house_id`, `type`, `severity`, `source`, `message`)
        VALUES (NEW.house_id, 'reseau', 'warning', NEW.mac_address,
                CONCAT('Nouvel appareil non identifié détecté sur le réseau (', NEW.mac_address, ')'));
    END IF;
END$$

-- Alerte automatique en cas de dépassement de seuil : la maison est
-- déduite par transitivité capteur -> pièce -> maison.
CREATE TRIGGER `trg_sensor_readings_after_insert`
AFTER INSERT ON `sensor_readings`
FOR EACH ROW
BEGIN
    DECLARE v_threshold DECIMAL(10,2);
    DECLARE v_sensor_name VARCHAR(100);
    DECLARE v_unit VARCHAR(20);
    DECLARE v_house_id INT UNSIGNED;

    SELECT s.`alert_threshold`, s.`name`, s.`unit`, r.`house_id`
        INTO v_threshold, v_sensor_name, v_unit, v_house_id
        FROM `sensors` s
        INNER JOIN `rooms` r ON r.`id` = s.`room_id`
        WHERE s.`id` = NEW.sensor_id;

    IF v_threshold IS NOT NULL AND NEW.value >= v_threshold THEN
        INSERT INTO `alerts` (`house_id`, `type`, `severity`, `source`, `message`)
        VALUES (v_house_id, 'capteur', 'critical', v_sensor_name,
                CONCAT('Seuil dépassé sur le capteur "', v_sensor_name, '" : ',
                       NEW.value, ' ', v_unit, ' (seuil : ', v_threshold, ' ', v_unit, ')'));
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- JEUX DE DONNÉES DE DÉMONSTRATION — DEUX MAISONS DISTINCTES
-- =====================================================================

-- Comptes utilisateurs. Mot de passe pour tous : "ViciaHome@2026"
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `status`) VALUES
(1, 'Support Vicia Home', 'admin@vicia-home.local', '$2y$10$dcIfBl5B5/X9stCW5Vl70uwcQlPUNzTLbPZYgwAeGroOfAYJ/4DAq', 'admin', 'active'),
(2, 'Arol Yemeli', 'arol@vicia-home.local', '$2y$10$dcIfBl5B5/X9stCW5Vl70uwcQlPUNzTLbPZYgwAeGroOfAYJ/4DAq', 'user', 'active'),
(3, 'Technicien Mobile', 'technicien@vicia-home.local', '$2y$10$dcIfBl5B5/X9stCW5Vl70uwcQlPUNzTLbPZYgwAeGroOfAYJ/4DAq', 'technicien', 'active'),
(4, 'Résidente Douala', 'resident@vicia-home.local', '$2y$10$dcIfBl5B5/X9stCW5Vl70uwcQlPUNzTLbPZYgwAeGroOfAYJ/4DAq', 'user', 'active');
-- Mot de passe initial commun des comptes de démonstration : ViciaHome@2026

-- Deux maisons distinctes, démontrant le multi-tenant.
INSERT INTO `houses` (`id`, `name`, `slug`, `address`, `city`, `alert_email`) VALUES
(1, 'Villa Yaoundé', 'villa-yaounde', 'Quartier Bastos', 'Yaoundé', 'arol@vicia-home.local'),
(2, 'Résidence Douala', 'residence-douala', 'Quartier Bonapriso', 'Douala', 'resident@vicia-home.local');

-- Affectations utilisateur <-> maison, avec rôle propre à chaque maison.
INSERT INTO `house_user` (`house_id`, `user_id`, `role_in_house`) VALUES
(1, 2, 'owner'),        -- Arol est propriétaire de la Villa Yaoundé
(1, 3, 'technician'),   -- Le technicien intervient sur la Villa Yaoundé
(2, 4, 'owner'),        -- Résidente Douala est propriétaire de sa maison
(2, 3, 'technician');   -- Le même technicien intervient aussi à Douala
-- Le compte "admin" (rôle plateforme) n'a pas besoin d'entrée dans
-- house_user : le support Vicia Home accède à toutes les maisons.

-- Pièces de la Villa Yaoundé (house_id = 1)
INSERT INTO `rooms` (`id`, `house_id`, `name`, `type`, `floor`, `icon`, `description`) VALUES
(1, 1, 'Salon', 'salon', 'Rez-de-chaussée', 'fa-couch', 'Pièce de vie principale'),
(2, 1, 'Cuisine', 'cuisine', 'Rez-de-chaussée', 'fa-utensils', 'Cuisine équipée'),
(3, 1, 'Chambre 1', 'chambre', 'Étage', 'fa-bed', 'Chambre principale'),
(4, 1, 'Garage', 'garage', 'Rez-de-chaussée', 'fa-warehouse', 'Garage avec porte motorisée'),
(5, 1, 'Bureau', 'bureau', 'Rez-de-chaussée', 'fa-briefcase', 'Local technique et bureau'),
(6, 1, 'Jardin', 'jardin', 'Extérieur', 'fa-leaf', 'Espace extérieur et arrosage');

-- Pièces de la Résidence Douala (house_id = 2) — namespace distinct
INSERT INTO `rooms` (`id`, `house_id`, `name`, `type`, `floor`, `icon`, `description`) VALUES
(7, 2, 'Salon', 'salon', 'Rez-de-chaussée', 'fa-couch', 'Salon principal'),
(8, 2, 'Chambre principale', 'chambre', 'Étage', 'fa-bed', 'Chambre parentale'),
(9, 2, 'Terrasse', 'terrasse', 'Extérieur', 'fa-umbrella-beach', 'Terrasse donnant sur le jardin');

-- Équipements — topics MQTT préfixés par le slug de la maison
-- (home/<slug>/...), garantissant ViciaHome@2026l'isolation des messages entre
-- maisons sur le même broker Mosquitto partagé.
INSERT INTO `equipments` (`room_id`, `name`, `type`, `icon`, `state`, `mqtt_topic`) VALUES
(1, 'Éclairage salon', 'led', 'fa-lightbulb', 1, 'home/villa-yaounde/lighting/salon/led1'),
(2, 'Éclairage cuisine', 'led', 'fa-lightbulb', 0, 'home/villa-yaounde/lighting/cuisine/led1'),
(3, 'Ventilateur chambre 1', 'ventilateur', 'fa-fan', 0, 'home/villa-yaounde/climate/chambre1/fan1'),
(4, 'Porte de garage', 'porte', 'fa-warehouse', 0, 'home/villa-yaounde/security/garage/door1'),
(1, 'Sirène d’alarme', 'sirene', 'fa-bell', 0, 'home/villa-yaounde/security/salon/siren1'),
(1, 'Caméra salon', 'camera', 'fa-video', 1, 'home/villa-yaounde/camera/salon/cam1'),
(7, 'Éclairage salon', 'led', 'fa-lightbulb', 1, 'home/residence-douala/lighting/salon/led1'),
(9, 'Pompe d’arrosage', 'pompe', 'fa-faucet', 0, 'home/residence-douala/garden/terrasse/pump1');

-- Capteurs — même logique de préfixage par maison
INSERT INTO `sensors` (`room_id`, `name`, `type`, `unit`, `icon`, `mqtt_topic`, `alert_threshold`) VALUES
(1, 'PIR Salon', 'pir', 'bool', 'fa-walking', 'home/villa-yaounde/security/salon/pir', NULL),
(3, 'Température chambre 1', 'dht22_temp', '°C', 'fa-temperature-high', 'home/villa-yaounde/climate/chambre1/temp', 35.00),
(3, 'Humidité chambre 1', 'dht22_hum', '%', 'fa-tint', 'home/villa-yaounde/climate/chambre1/hum', NULL),
(2, 'Détecteur de gaz cuisine', 'mq2', 'ppm', 'fa-smog', 'home/villa-yaounde/safety/cuisine/mq2', 400.00),
(6, 'Humidité du sol jardin', 'humidite_sol', '%', 'fa-seedling', 'home/villa-yaounde/garden/jardin/soil', NULL),
(7, 'PIR Salon', 'pir', 'bool', 'fa-walking', 'home/residence-douala/security/salon/pir', NULL),
(8, 'Température chambre', 'dht22_temp', '°C', 'fa-temperature-high', 'home/residence-douala/climate/chambre/temp', 33.00);

INSERT INTO `sensor_readings` (`sensor_id`, `value`, `recorded_at`) VALUES
(2, 24.5, NOW() - INTERVAL 3 HOUR), (2, 25.1, NOW() - INTERVAL 2 HOUR), (2, 26.3, NOW() - INTERVAL 1 HOUR), (2, 25.8, NOW()),
(3, 55.0, NOW() - INTERVAL 3 HOUR), (3, 57.2, NOW() - INTERVAL 2 HOUR), (3, 54.8, NOW() - INTERVAL 1 HOUR), (3, 56.0, NOW()),
(7, 27.0, NOW() - INTERVAL 2 HOUR), (7, 28.4, NOW() - INTERVAL 1 HOUR), (7, 27.9, NOW());

-- Appareils réseau, scopés par maison
INSERT INTO `network_devices` (`house_id`, `mac_address`, `ip_address`, `hostname`, `vendor`, `list_status`, `is_blocked`) VALUES
(1, 'AA:BB:CC:11:22:33', '192.168.20.10', 'esp32-salon', 'Espressif', 'whitelisted', 0),
(1, 'AA:BB:CC:11:22:44', '192.168.20.11', 'esp32-cuisine', 'Espressif', 'whitelisted', 0),
(1, 'BB:CC:DD:55:66:77', '192.168.20.55', 'unknown-device', NULL, 'unknown', 0),
(2, 'AA:BB:CC:99:88:77', '192.168.30.10', 'esp32-salon-douala', 'Espressif', 'whitelisted', 0);

-- Règles d'automatisation, scopées par maison
INSERT INTO `automation_rules`
(`house_id`, `name`, `condition_source`, `condition_sensor_id`, `condition_operator`, `condition_value`, `action_equipment_id`, `action_state`, `notify_telegram`, `notify_email`, `is_active`) VALUES
(1, 'Extinction ventilateur si température basse', 'sensor', 2, '<', '20', 3, 0, 0, 0, 1),
(1, 'Alerte gaz cuisine', 'sensor', 4, '>=', '400', NULL, NULL, 1, 1, 1);

-- Paramètres globaux de la plateforme (hors maisons)
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Vicia Home'),
('theme_mode', 'light'),
('smtp_host', ''),
('smtp_from', 'no-reply@vicia-home.local');
