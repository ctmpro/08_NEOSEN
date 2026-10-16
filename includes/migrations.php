<?php
/**
 * Migrations de la base : crée les tables manquantes et ajoute les colonnes ajoutées
 * dans les versions suivantes, sans jamais supprimer de données.
 *
 * Exécutées automatiquement à l'ouverture de l'administration quand la version change,
 * ainsi que par install/install.php. Les tables sont créées à partir de install/schema.sql (à conserver).
 */

const DB_VERSION = 3;

/** Colonnes ajoutées après la création initiale des tables : [table, colonne, définition]. */
function db_added_columns(): array {
    return [
        ['crm_documents', 'is_deposit', "TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Facture d''acompte' AFTER deposit_pct"],
        ['crm_documents', 'operation_type', "VARCHAR(20) NOT NULL DEFAULT 'services' AFTER is_deposit"],
        ['crm_documents', 'seller_snapshot', 'TEXT NULL AFTER operation_type'],
        ['crm_documents', 'terms_snapshot', 'TEXT NULL AFTER seller_snapshot'],
        ['crm_documents', 'pdf_path', 'VARCHAR(255) NULL AFTER terms_snapshot'],
        ['crm_documents', 'pdf_hash', 'CHAR(64) NULL AFTER pdf_path'],
    ];
}

function db_migrate(PDO $pdo): void {
    $schemaFile = dirname(__DIR__) . '/install/schema.sql';
    if (is_file($schemaFile)) {
        $schema = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($schemaFile));
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }
    }
    foreach (db_added_columns() as [$table, $column, $definition]) {
        $exists = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE " . $pdo->quote($column))->fetch();
        if (!$exists) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
    $pdo->prepare("INSERT INTO settings (setting_key, lang, setting_value) VALUES ('db_version', 'fr', ?)
                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([(string) DB_VERSION]);
}

/** Lance les migrations si la base n'est pas à jour (silencieux en cas d'échec, consigné dans les logs). */
function db_migrate_if_needed(PDO $pdo): void {
    try {
        $v = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'db_version' AND lang = 'fr'")->fetchColumn();
        if ((int) $v < DB_VERSION) {
            db_migrate($pdo);
        }
    } catch (Throwable $e) {
        error_log('NEOSEN: migration de la base impossible — ' . $e->getMessage());
    }
}
