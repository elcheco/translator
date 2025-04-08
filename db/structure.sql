-- Database structure for ElCheco Translator

-- Modules table - groups of translation keys
CREATE TABLE `translation_modules` (
                                       `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                                       `name` VARCHAR(100) NOT NULL,
                                       `description` VARCHAR(255) NULL,
                                       `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                                       `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                       `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                       PRIMARY KEY (`id`),
                                       UNIQUE INDEX `translation_modules_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Translation keys table
CREATE TABLE `translation_keys` (
                                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                                    `module_id` INT UNSIGNED NOT NULL,
                                    `key` VARCHAR(255) NOT NULL,
                                    `type` ENUM('text', 'html', 'plural') NOT NULL DEFAULT 'text',
                                    `description` TEXT NULL,
                                    `ai_instructions` TEXT NULL,
                                    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0,
                                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                    PRIMARY KEY (`id`),
                                    UNIQUE INDEX `translation_keys_module_id_key_unique` (`module_id`, `key`),
                                    CONSTRAINT `translation_keys_module_id_foreign`
                                        FOREIGN KEY (`module_id`)
                                            REFERENCES `translation_modules` (`id`)
                                            ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Translations table
CREATE TABLE `translations` (
                                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                                `key_id` INT UNSIGNED NOT NULL,
                                `locale` VARCHAR(10) NOT NULL,
                                `value` TEXT NULL,
                                `plural_values` JSON NULL COMMENT 'JSON object containing plural forms',
                                `is_translated` TINYINT(1) NOT NULL DEFAULT 0,
                                `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
                                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                PRIMARY KEY (`id`),
                                UNIQUE INDEX `translations_key_id_locale_unique` (`key_id`, `locale`),
                                CONSTRAINT `translations_key_id_foreign`
                                    FOREIGN KEY (`key_id`)
                                        REFERENCES `translation_keys` (`id`)
                                        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data
INSERT INTO `translation_modules` (`name`, `description`) VALUES
                                                              ('Common', 'Common website translations'),
                                                              ('Admin', 'Admin interface translations');

INSERT INTO `translation_keys` (`module_id`, `key`, `type`, `description`) VALUES
                                                                               ((SELECT id FROM `translation_modules` WHERE name = 'Common'), 'Welcome to our website', 'text', 'Homepage welcome message'),
                                                                               ((SELECT id FROM `translation_modules` WHERE name = 'Common'), 'Hello %s', 'text', 'Greeting with username parameter'),
                                                                               ((SELECT id FROM `translation_modules` WHERE name = 'Common'), 'You have %s new messages', 'plural', 'Notification about new messages');

-- English translations
INSERT INTO `translations` (`key_id`, `locale`, `value`, `plural_values`, `is_translated`, `is_approved`) VALUES
                                                                                                              ((SELECT id FROM `translation_keys` WHERE `key` = 'Welcome to our website'), 'en_US', 'Welcome to our website', NULL, 1, 1),
                                                                                                              ((SELECT id FROM `translation_keys` WHERE `key` = 'Hello %s'), 'en_US', 'Hello %s', NULL, 1, 1),
                                                                                                              ((SELECT id FROM `translation_keys` WHERE `key` = 'You have %s new messages'), 'en_US', NULL, '{"1":"You have %s new message","2":"You have %s new messages"}', 1, 1);

-- Czech translations
INSERT INTO `translations` (`key_id`, `locale`, `value`, `plural_values`, `is_translated`, `is_approved`) VALUES
                                                                                                              ((SELECT id FROM `translation_keys` WHERE `key` = 'Welcome to our website'), 'cs_CZ', 'Vítejte na našem webu', NULL, 1, 1),
                                                                                                              ((SELECT id FROM `translation_keys` WHERE `key` = 'Hello %s'), 'cs_CZ', 'Ahoj %s', NULL, 1, 1),
                                                                                                              ((SELECT id FROM `translation_keys` WHERE `key` = 'You have %s new messages'), 'cs_CZ', NULL, '{"1":"Máte %s novou zprávu","2":"Máte %s nové zprávy","5":"Máte %s nových zpráv"}', 1, 1);
