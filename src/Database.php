<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            $config = require __DIR__ . '/config.php';
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['db']['host'],
                $config['db']['name'],
                $config['db']['charset']
            );
            self::$pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pdo;
    }
}
