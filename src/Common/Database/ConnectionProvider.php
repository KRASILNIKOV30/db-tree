<?php
declare(strict_types=1);

namespace App\Common\Database;

final class ConnectionProvider
{
    public static function getConnection(): Connection
    {
        static $connection = null;
        if ($connection === null)
        {
            $dsn = self::getEnvString('APP_DATABASE_DSN', 'mysql:dbname=tree_of_life;host=localhost');
            $user = self::getEnvString('APP_DATABASE_USER', 'root');
            $password = self::getEnvString('APP_DATABASE_PASSWORD', 'Zakunbor7839');
            $connection = new Connection($dsn, $user, $password);
        }
        return $connection;
    }

    private static function getEnvString(string $name, string $defaultValue): string
    {
        $value = getenv($name);
        if ($value === false)
        {
            return $defaultValue;
        }
        return (string)$value;
    }
}
