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
                                `is_approved` TINYINT(1) NOT NULL DEFAULT 0,
                                `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
                                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                PRIMARY KEY (`id`),
                                UNIQUE INDEX `translations_key_id_locale_unique` (`key_id`, `locale`),
                                CONSTRAINT `translations_key_id_foreign`
                                    FOREIGN KEY (`key_id`)
                                        REFERENCES `translation_keys` (`id`)
                                        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration script to add CLDR support to ElCheco Translator database

-- Add format_type column to translation_keys table
ALTER TABLE `translation_keys`
    ADD COLUMN `format_type` ENUM('sprintf', 'icu') NOT NULL DEFAULT 'sprintf' AFTER `type`;

-- Add cldr_message_pattern column for storing ICU patterns
ALTER TABLE `translation_keys`
    ADD COLUMN `cldr_message_pattern` TEXT NULL AFTER `ai_instructions`;

-- Add index for format_type for better query performance
ALTER TABLE `translation_keys`
    ADD INDEX `idx_format_type` (`format_type`);

-- Update existing plural translations to have format_type
UPDATE `translation_keys`
SET `format_type` = 'sprintf'
WHERE `type` = 'plural';

-- Create a view for easier CLDR translations management
CREATE VIEW `cldr_translations` AS
SELECT
    tm.name AS module_name,
    tk.key,
    tk.type,
    tk.format_type,
    tk.cldr_message_pattern,
    t.locale,
    t.value,
    t.plural_values,
    t.is_approved,
    t.is_locked,
    t.updated_at
FROM `translation_keys` tk
         JOIN `translation_modules` tm ON tk.module_id = tm.id
         LEFT JOIN `translations` t ON tk.id = t.key_id
WHERE tk.format_type = 'icu'
ORDER BY tm.name, tk.key, t.locale;

-- Create a stored procedure to convert legacy plurals to CLDR
DELIMITER //

CREATE PROCEDURE `ConvertPluralToCldr`(
    IN p_key_id INT,
    IN p_locale VARCHAR(10),
    IN p_cldr_forms JSON
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
        BEGIN
            ROLLBACK;
            RESIGNAL;
        END;

    START TRANSACTION;

    -- Update the key to ICU format
    UPDATE `translation_keys`
    SET `format_type` = 'icu'
    WHERE `id` = p_key_id;

    -- Update the translation with CLDR forms
    UPDATE `translations`
    SET `plural_values` = p_cldr_forms,
        `updated_at` = CURRENT_TIMESTAMP
    WHERE `key_id` = p_key_id AND `locale` = p_locale;

    COMMIT;
END//

DELIMITER ;

-- Create a function to check if a translation uses CLDR
DELIMITER //

CREATE FUNCTION `IsCldrTranslation`(p_key_id INT)
    RETURNS BOOLEAN
    DETERMINISTIC
    READS SQL DATA
BEGIN
    DECLARE v_format_type VARCHAR(10);

    SELECT `format_type` INTO v_format_type
    FROM `translation_keys`
    WHERE `id` = p_key_id;

    RETURN v_format_type = 'icu';
END//

DELIMITER ;

-- Sample data
INSERT INTO `translation_modules` (`name`, `description`) VALUES
                                                              ('Common', 'Common website translations'),
                                                              ('Admin', 'Admin interface translations');

-- Add sample CLDR translations for demonstration
INSERT INTO `translation_keys` (`module_id`, `key`, `type`, `format_type`, `description`) VALUES
                                                                                              ((SELECT id FROM `translation_modules` WHERE name = 'Common'), 'items_count', 'plural', 'icu', 'Item count with CLDR format'),
                                                                                              ((SELECT id FROM `translation_modules` WHERE name = 'Common'), 'days_remaining', 'plural', 'icu', 'Days remaining with CLDR format');

-- English CLDR translations
INSERT INTO `translations` (`key_id`, `locale`, `value`, `plural_values`, `is_locked`, `is_approved`) VALUES
                                                                                                          ((SELECT id FROM `translation_keys` WHERE `key` = 'items_count'), 'en_US', NULL,
                                                                                                           '{"zero":"You have no items","one":"You have one item","other":"You have {count} items"}', 1, 1),
                                                                                                          ((SELECT id FROM `translation_keys` WHERE `key` = 'days_remaining'), 'en_US', NULL,
                                                                                                           '{"zero":"Today is the last day","one":"One day remaining","other":"{count} days remaining"}', 1, 1);

-- Czech CLDR translations
INSERT INTO `translations` (`key_id`, `locale`, `value`, `plural_values`, `is_locked`, `is_approved`) VALUES
                                                                                                          ((SELECT id FROM `translation_keys` WHERE `key` = 'items_count'), 'cs_CZ', NULL,
                                                                                                           '{"zero":"Nemáte žádné položky","one":"Máte jednu položku","few":"Máte {count} položky","other":"Máte {count} položek"}', 1, 1),
                                                                                                          ((SELECT id FROM `translation_keys` WHERE `key` = 'days_remaining'), 'cs_CZ', NULL,
                                                                                                           '{"zero":"Dnes je poslední den","one":"Zbývá jeden den","few":"Zbývají {count} dny","other":"Zbývá {count} dní"}', 1, 1);

-- Create an audit table for tracking format conversions
CREATE TABLE `translation_format_conversions` (
                                                  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                                                  `key_id` INT UNSIGNED NOT NULL,
                                                  `locale` VARCHAR(10) NOT NULL,
                                                  `from_format` VARCHAR(10) NOT NULL,
                                                  `to_format` VARCHAR(10) NOT NULL,
                                                  `original_values` JSON NULL,
                                                  `converted_values` JSON NULL,
                                                  `converted_by` VARCHAR(100) NULL,
                                                  `converted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                  PRIMARY KEY (`id`),
                                                  INDEX `idx_conversion_key_locale` (`key_id`, `locale`),
                                                  CONSTRAINT `fk_conversion_key_id`
                                                      FOREIGN KEY (`key_id`)
                                                          REFERENCES `translation_keys` (`id`)
                                                          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
