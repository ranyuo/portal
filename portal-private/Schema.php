<?php
declare(strict_types=1);
final class PortalSchema
{
    public const VERSION = 2;
    public static function apply(PDO $db): void
    {
        foreach (explode(';', file_get_contents(__DIR__.'/schema.sql')) as $sql) if (trim($sql) !== '') $db->exec($sql);
        $columns = array_column($db->query('SHOW COLUMNS FROM pisco_users')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        foreach (['role' => "VARCHAR(30) NOT NULL DEFAULT 'admin'", 'profile' => 'JSON NULL',
            'auth_version' => 'INT UNSIGNED NOT NULL DEFAULT 1', 'login_failures' => 'INT UNSIGNED NOT NULL DEFAULT 0',
            'locked_until' => 'DATETIME NULL', 'last_login' => 'DATETIME NULL'] as $name => $type) {
            if (!in_array($name, $columns, true)) $db->exec('ALTER TABLE pisco_users ADD COLUMN '.$name.' '.$type);
        }
        $db->prepare('INSERT INTO pisco_schema (id,version) VALUES (1,?) ON DUPLICATE KEY UPDATE version = VALUES(version)')->execute([self::VERSION]);
    }
}
