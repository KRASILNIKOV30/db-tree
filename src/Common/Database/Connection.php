<?php
declare(strict_types=1);

namespace App\Common\Database;

final class Connection
{
    private string $dsn;
    private string $user;
    private string $password;

    private ?\PDO $handle = null;
    private int $transactionLevel = 0;

    /**
     * @param string $dsn - DSN, например 'mysql:dbname=testdb;host=127.0.0.1'
     * @param string $user - имя пользователя MySQL
     * @param string $password - пароль пользователя MySQL
     */
    public function __construct(string $dsn, string $user, string $password)
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
    }

    /**
     * Выполняет запрос с подстановкой параметров.
     * Подстановка параметров даёт устойчивость к SQL Injections.
     *
     * @param string $sql
     * @param array $params
     * @return \PDOStatement
     */
    public function execute(string $sql, array $params = []): \PDOStatement
    {
        $statement = $this->getConnection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Подготавливает запрос к выполнению с подстановкой параметров.
     * Подстановка параметров даёт устойчивость к SQL Injections.
     *
     * @param string $sql
     * @return \PDOStatement
     */
    public function prepare(string $sql): \PDOStatement
    {
        return $this->getConnection()->prepare($sql);
    }

    public function getLastInsertId(): int
    {
        if ($lastInsertId = $this->getConnection()->lastInsertId())
        {
            return (int)$lastInsertId;
        }
        return throw new \RuntimeException("Failed to get last insert id");
    }

    public function beginTransaction(): void
    {
        if ($this->transactionLevel === 0)
        {
            $this->getConnection()->beginTransaction();
        }
        ++$this->transactionLevel;
    }

    public function commit(): void
    {
        if ($this->transactionLevel < 0)
        {
            throw new \RuntimeException('Cannot call ' . __METHOD__ . ': there is no open transaction');
        }

        --$this->transactionLevel;
        if ($this->transactionLevel === 0)
        {
            $this->getConnection()->commit();
        }
    }

    public function rollback(): void
    {
        if ($this->transactionLevel < 0)
        {
            throw new \RuntimeException('Cannot call ' . __METHOD__ . ': there is no open transaction');
        }

        --$this->transactionLevel;
        if ($this->transactionLevel === 0)
        {
            $this->getConnection()->rollBack();
        }
    }

    /**
     * Выполняет ленивое создание объекта PDO, абстрагирующего соединение с базой данных.
     * Устанавливает параметры:
     *  1. режим обработки ошибок: выброс исключений
     *
     * @return \PDO
     */
    private function getConnection(): \PDO
    {
        if ($this->handle === null)
        {
            $this->handle = new \PDO($this->dsn, $this->user, $this->password);
            $this->handle->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }
        return $this->handle;
    }
}
