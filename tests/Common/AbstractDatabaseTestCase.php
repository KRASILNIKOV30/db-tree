<?php
declare(strict_types=1);

namespace Tests\App\Common;

use App\Common\Database\Connection;
use App\Common\Database\ConnectionProvider;
use PHPUnit\Framework\TestCase;

abstract class AbstractDatabaseTestCase extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionProvider::getConnection();
        $this->connection->beginTransaction();

    }

    protected function tearDown(): void
    {
        // Always rollback transaction - no changes applied to test database data.
        $this->connection->rollback();
        parent::tearDown();
    }

    final protected function getConnection(): Connection
    {
        return $this->connection;
    }
}
