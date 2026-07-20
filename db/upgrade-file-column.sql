-- Upgrade for existing installations: adds the `file` dimension to translation keys
-- so one module can hold the same key with different values per generated file.
-- New installations get this from structure.sql directly.
--
-- The new unique index is added before dropping the old one so the module_id
-- foreign key always has a usable index.

ALTER TABLE `translation_keys`
    ADD COLUMN `file` VARCHAR(100) NOT NULL DEFAULT '' AFTER `module_id`;

ALTER TABLE `translation_keys`
    ADD UNIQUE INDEX `translation_keys_module_id_file_key_unique` (`module_id`, `file`, `key`);

ALTER TABLE `translation_keys`
    DROP INDEX `translation_keys_module_id_key_unique`;
