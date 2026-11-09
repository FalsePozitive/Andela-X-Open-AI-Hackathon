<?php
declare(strict_types=1);
namespace Passport;

final class Database
{
    public static function connect(array $config): \PDO
    {
        $pdo = new \PDO($config['dsn'], $config['db_user'], $config['db_password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }

    public static function run(\PDO $db, string $sql, array $params = []): \PDOStatement
    {
        $query = $db->prepare($sql);
        $query->execute($params);
        return $query;
    }

    public static function migrate(\PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS migrations (version VARCHAR(100) PRIMARY KEY, applied_at DATETIME(6) NOT NULL) ENGINE=InnoDB');
        foreach (glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [] as $file) {
            $version = basename($file);
            if (!self::run($db, 'SELECT version FROM migrations WHERE version=?', [$version])->fetch()) {
                $sql = (string) file_get_contents($file);
                foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
                    if (trim($statement) !== '') {
                        $db->exec($statement);
                    }
                }
                self::run($db, 'INSERT INTO migrations VALUES (?, UTC_TIMESTAMP(6))', [$version]);
            }
        }
    }
}
