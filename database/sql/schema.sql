-- =====================================================================
-- VICIA HOME — Plateforme de gestion de maison intelligente
-- Script de création de la base de données
-- SGBD cible : MySQL 8.0+
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `vicia_home2`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `vicia_home2`;

-- ---------------------------------------------------------------------
-- Table : users
-- Comptes utilisateurs de la plateforme (administrateur, utilisateur,
-- technicien). Les mots de passe sont stockés hachés avec BCrypt.
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`              VARCHAR(100)        NOT NULL,
    `email`             VARCHAR(150)        NOT NULL,
    `password_hash`     VARCHAR(255)        NOT NULL,
    `role`              ENUM('admin','user','technicien') NOT NULL DEFAULT 'user',
    `avatar`            VARCHAR(255)        NULL,
    `phone`             VARCHAR(30)         NULL,
    `remember_token`    VARCHAR(100)        NULL,
    `status`            ENUM('active','suspended') NOT NULL DEFAULT 'active',
    `last_login_at`     DATETIME            NULL,
    `created_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : password_resets
-- Jetons de réinitialisation de mot de passe (fonction "mot de passe
-- oublié").
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
-- Historique des connexions (réussies et échouées) à la plateforme.
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
-- Table : rooms
-- Pièces de l'habitation (salon, cuisine, chambre, garage...).
-- ---------------------------------------------------------------------
CREATE TABLE `rooms` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100)    NOT NULL,
    `type`          ENUM('salon','cuisine','chambre','garage','bureau','salle_de_bain','jardin','terrasse','autre') NOT NULL DEFAULT 'autre',
    `floor`         VARCHAR(50)     NULL,
    `icon`          VARCHAR(50)     NOT NULL DEFAULT 'fa-door-open',
    `description`   VARCHAR(255)    NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : equipments
-- Équipements pilotables (actionneurs) installés dans les pièces.
-- ---------------------------------------------------------------------
CREATE TABLE `equipments` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id`       INT UNSIGNED    NOT NULL,
    `name`          VARCHAR(100)    NOT NULL,
    `type`          ENUM('led','relais','ventilateur','pompe','servo','porte','fenetre','sirene','camera') NOT NULL,
    `icon`          VARCHAR(50)     NOT NULL DEFAULT 'fa-lightbulb',
    `state`         TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '0 = éteint/fermé, 1 = allumé/ouvert',
    `mqtt_topic`    VARCHAR(150)    NOT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1 COMMENT 'équipement activé/désactivé dans le système',
    `last_state_change` DATETIME    NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_equipments_topic` (`mqtt_topic`),
    KEY `idx_equipments_room` (`room_id`),
    CONSTRAINT `fk_equipments_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : sensors
-- Capteurs installés dans les pièces (entrées de mesure).
-- ---------------------------------------------------------------------
CREATE TABLE `sensors` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_id`       INT UNSIGNED    NOT NULL,
    `name`          VARCHAR(100)    NOT NULL,
    `type`          ENUM('pir','dht22_temp','dht22_hum','mq2','mq135','ldr','rfid','humidite_sol') NOT NULL,
    `unit`          VARCHAR(20)     NOT NULL DEFAULT '',
    `icon`          VARCHAR(50)     NOT NULL DEFAULT 'fa-microchip',
    `mqtt_topic`    VARCHAR(150)    NOT NULL,
    `alert_threshold` DECIMAL(10,2) NULL COMMENT 'seuil déclenchant une alerte automatique',
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_sensors_topic` (`mqtt_topic`),
    KEY `idx_sensors_room` (`room_id`),
    CONSTRAINT `fk_sensors_room` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : sensor_readings
-- Historique des mesures relevées par les capteurs.
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
-- Alertes générées par le système (sécurité, capteurs, réseau).
-- ---------------------------------------------------------------------
CREATE TABLE `alerts` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type`          VARCHAR(50)     NOT NULL COMMENT 'intrusion, reseau, capteur, systeme',
    `severity`      ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    `source`        VARCHAR(100)    NULL COMMENT 'origine de l’alerte (ex. nom du capteur, adresse MAC)',
    `message`       VARCHAR(255)    NOT NULL,
    `is_read`       TINYINT(1)      NOT NULL DEFAULT 0,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_alerts_read` (`is_read`),
    KEY `idx_alerts_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : network_devices
-- Appareils détectés sur le réseau domestique (module cybersécurité).
-- ---------------------------------------------------------------------
CREATE TABLE `network_devices` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `mac_address`   VARCHAR(17)     NOT NULL,
    `ip_address`    VARCHAR(45)     NULL,
    `hostname`      VARCHAR(150)    NULL,
    `vendor`        VARCHAR(150)    NULL,
    `list_status`   ENUM('unknown','whitelisted','blacklisted') NOT NULL DEFAULT 'unknown',
    `first_seen`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_seen`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_blocked`    TINYINT(1)      NOT NULL DEFAULT 0,
    UNIQUE KEY `uq_network_devices_mac` (`mac_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : network_logs
-- Journal des événements réseau (connexion, scan, tentative bloquée).
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
-- Règles d'automatisation définies par l'utilisateur (moteur de règles).
-- ---------------------------------------------------------------------
CREATE TABLE `automation_rules` (
    `id`                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`                  VARCHAR(150)    NOT NULL,
    `condition_source`      ENUM('sensor','event','time') NOT NULL DEFAULT 'sensor',
    `condition_sensor_id`   INT UNSIGNED    NULL,
    `condition_operator`    ENUM('>','<','>=','<=','=','!=') NULL,
    `condition_value`       VARCHAR(50)     NULL,
    `condition_event`       VARCHAR(50)     NULL COMMENT 'ex: intrusion, appareil_inconnu',
    `action_equipment_id`   INT UNSIGNED    NULL,
    `action_state`          TINYINT(1)      NULL COMMENT 'état à appliquer à l’équipement cible',
    `notify_telegram`       TINYINT(1)      NOT NULL DEFAULT 0,
    `notify_email`          TINYINT(1)      NOT NULL DEFAULT 0,
    `is_active`             TINYINT(1)      NOT NULL DEFAULT 1,
    `created_by`            INT UNSIGNED    NULL,
    `created_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_rules_sensor` (`condition_sensor_id`),
    KEY `idx_rules_equipment` (`action_equipment_id`),
    CONSTRAINT `fk_rules_sensor` FOREIGN KEY (`condition_sensor_id`) REFERENCES `sensors` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rules_equipment` FOREIGN KEY (`action_equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rules_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : automation_logs
-- Historique d'exécution des règles d'automatisation.
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
-- Journal général des activités utilisateur (audit).
-- ---------------------------------------------------------------------
CREATE TABLE `activity_logs` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT UNSIGNED    NULL,
    `action`        VARCHAR(100)    NOT NULL,
    `description`   VARCHAR(255)    NULL,
    `ip_address`    VARCHAR(45)     NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_activity_logs_user` (`user_id`),
    CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Table : mqtt_logs
-- Journal des messages MQTT échangés (diagnostic et traçabilité).
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
-- Paramètres généraux de la plateforme (clé/valeur).
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

-- À chaque changement d'état d'un équipement, on horodate le changement.
CREATE TRIGGER `trg_equipments_before_update`
BEFORE UPDATE ON `equipments`
FOR EACH ROW
BEGIN
    IF NEW.state <> OLD.state THEN
        SET NEW.last_state_change = NOW();
    END IF;
END$$

-- Détection automatique d'un appareil inconnu sur le réseau : génère
-- une alerte de sécurité dès l'insertion d'un nouvel appareil non classé.
CREATE TRIGGER `trg_network_devices_after_insert`
AFTER INSERT ON `network_devices`
FOR EACH ROW
BEGIN
    IF NEW.list_status = 'unknown' THEN
        INSERT INTO `alerts` (`type`, `severity`, `source`, `message`)
        VALUES ('reseau', 'warning', NEW.mac_address,
                CONCAT('Nouvel appareil non identifié détecté sur le réseau (', NEW.mac_address, ')'));
    END IF;
END$$

-- Alerte automatique en cas de dépassement de seuil sur une mesure de
-- capteur (ex. gaz MQ-2/MQ-135, seuil défini au niveau du capteur).
CREATE TRIGGER `trg_sensor_readings_after_insert`
AFTER INSERT ON `sensor_readings`
FOR EACH ROW
BEGIN
    DECLARE v_threshold DECIMAL(10,2);
    DECLARE v_sensor_name VARCHAR(100);
    DECLARE v_unit VARCHAR(20);

    SELECT `alert_threshold`, `name`, `unit`
        INTO v_threshold, v_sensor_name, v_unit
        FROM `sensors`
        WHERE `id` = NEW.sensor_id;

    IF v_threshold IS NOT NULL AND NEW.value >= v_threshold THEN
        INSERT INTO `alerts` (`type`, `severity`, `source`, `message`)
        VALUES ('capteur', 'critical', v_sensor_name,
                CONCAT('Seuil dépassé sur le capteur "', v_sensor_name, '" : ',
                       NEW.value, ' ', v_unit, ' (seuil : ', v_threshold, ' ', v_unit, ')'));
    END IF;
END$$

DELIMITER ;

-- =====================================================================
-- JEUX DE DONNÉES DE DÉMONSTRATION
-- =====================================================================

-- Utilisateur administrateur par défaut : mot de passe "ViciaHome@2026"
-- (haché avec password_hash() / BCrypt — à changer après la première connexion)
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `status`) VALUES
('Administrateur Vicia', 'admin@vicia-home.local', '$2y$10$dcIfBl5B5/X9stCW5Vl70uwcQlPUNzTLbPZYgwAeGroOfAYJ/4DAq', 'admin', 'active'),
('Technicien Support', 'technicien@vicia-home.local', '$2y$10$dcIfBl5B5/X9stCW5Vl70uwcQlPUNzTLbPZYgwAeGroOfAYJ/4DAq', 'technicien', 'active'),
('Résident Principal', 'resident@vicia-home.local', '$2y$10$dcIfBl5B5/X9stCW5Vl70uwcQlPUNzTLbPZYgwAeGroOfAYJ/4DAq', 'user', 'active');

INSERT INTO `rooms` (`name`, `type`, `floor`, `icon`, `description`) VALUES
('Salon', 'salon', 'Rez-de-chaussée', 'fa-couch', 'Pièce de vie principale'),
('Cuisine', 'cuisine', 'Rez-de-chaussée', 'fa-utensils', 'Cuisine équipée'),
('Chambre 1', 'chambre', 'Étage', 'fa-bed', 'Chambre principale'),
('Chambre 2', 'chambre', 'Étage', 'fa-bed', 'Chambre secondaire'),
('Garage', 'garage', 'Rez-de-chaussée', 'fa-warehouse', 'Garage avec porte motorisée'),
('Bureau', 'bureau', 'Rez-de-chaussée', 'fa-briefcase', 'Local technique et bureau'),
('Salle de bain', 'salle_de_bain', 'Étage', 'fa-bath', 'Salle d’eau'),
('Jardin', 'jardin', 'Extérieur', 'fa-leaf', 'Espace extérieur et arrosage'),
('Terrasse', 'terrasse', 'Extérieur', 'fa-umbrella-beach', 'Terrasse extérieure');

INSERT INTO `equipments` (`room_id`, `name`, `type`, `icon`, `state`, `mqtt_topic`) VALUES
(1, 'Éclairage salon', 'led', 'fa-lightbulb', 1, 'home/lighting/salon/led1'),
(2, 'Éclairage cuisine', 'led', 'fa-lightbulb', 0, 'home/lighting/cuisine/led1'),
(3, 'Ventilateur chambre 1', 'ventilateur', 'fa-fan', 0, 'home/climate/chambre1/fan1'),
(5, 'Porte de garage', 'porte', 'fa-warehouse', 0, 'home/security/garage/door1'),
(5, 'Éclairage garage', 'relais', 'fa-lightbulb', 0, 'home/lighting/garage/relais1'),
(8, 'Pompe d’arrosage', 'pompe', 'fa-faucet', 0, 'home/garden/jardin/pump1'),
(1, 'Sirène d’alarme', 'sirene', 'fa-bell', 0, 'home/security/salon/siren1'),
(5, 'Portail motorisé', 'servo', 'fa-door-open', 0, 'home/security/portail/servo1'),
(7, 'Fenêtre salle de bain', 'fenetre', 'fa-window-maximize', 0, 'home/security/sdb/window1'),
(1, 'Caméra salon', 'camera', 'fa-video', 1, 'home/camera/salon/cam1');

INSERT INTO `sensors` (`room_id`, `name`, `type`, `unit`, `icon`, `mqtt_topic`, `alert_threshold`) VALUES
(1, 'PIR Salon', 'pir', 'bool', 'fa-walking', 'home/security/salon/pir', NULL),
(3, 'Température chambre 1', 'dht22_temp', '°C', 'fa-temperature-high', 'home/climate/chambre1/temp', 35.00),
(3, 'Humidité chambre 1', 'dht22_hum', '%', 'fa-tint', 'home/climate/chambre1/hum', NULL),
(2, 'Détecteur de gaz cuisine', 'mq2', 'ppm', 'fa-smog', 'home/safety/cuisine/mq2', 400.00),
(6, 'Qualité de l’air bureau', 'mq135', 'ppm', 'fa-wind', 'home/safety/bureau/mq135', 800.00),
(8, 'Luminosité jardin', 'ldr', 'lux', 'fa-sun', 'home/garden/jardin/ldr', NULL),
(5, 'Lecteur RFID portail', 'rfid', 'uid', 'fa-id-card', 'home/security/portail/rfid', NULL),
(8, 'Humidité du sol jardin', 'humidite_sol', '%', 'fa-seedling', 'home/garden/jardin/soil', NULL);

INSERT INTO `sensor_readings` (`sensor_id`, `value`, `recorded_at`) VALUES
(2, 24.5, NOW() - INTERVAL 3 HOUR), (2, 25.1, NOW() - INTERVAL 2 HOUR), (2, 26.3, NOW() - INTERVAL 1 HOUR), (2, 25.8, NOW()),
(3, 55.0, NOW() - INTERVAL 3 HOUR), (3, 57.2, NOW() - INTERVAL 2 HOUR), (3, 54.8, NOW() - INTERVAL 1 HOUR), (3, 56.0, NOW()),
(6, 320.0, NOW() - INTERVAL 3 HOUR), (6, 410.0, NOW() - INTERVAL 2 HOUR), (6, 180.0, NOW() - INTERVAL 1 HOUR), (6, 260.0, NOW());

INSERT INTO `network_devices` (`mac_address`, `ip_address`, `hostname`, `vendor`, `list_status`, `is_blocked`) VALUES
('AA:BB:CC:11:22:33', '192.168.20.10', 'esp32-salon', 'Espressif', 'whitelisted', 0),
('AA:BB:CC:11:22:44', '192.168.20.11', 'esp32-cuisine', 'Espressif', 'whitelisted', 0),
('BB:CC:DD:55:66:77', '192.168.20.55', 'unknown-device', NULL, 'unknown', 0);

INSERT INTO `automation_rules`
(`name`, `condition_source`, `condition_sensor_id`, `condition_operator`, `condition_value`, `action_equipment_id`, `action_state`, `notify_telegram`, `notify_email`, `is_active`) VALUES
('Extinction ventilateur si température basse', 'sensor', 2, '<', '20', 3, 0, 0, 0, 1),
('Alerte gaz cuisine', 'sensor', 4, '>=', '400', NULL, NULL, 1, 1, 1);

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Vicia Home'),
('theme_mode', 'light'),
('dashboard_mode', 'comfort'),
('telegram_bot_token', ''),
('telegram_chat_id', ''),
('smtp_host', ''),
('smtp_from', 'no-reply@vicia-home.local');
