<?php

namespace App\TreeOfLife\Service;

use App\Common\Database\Connection;
use App\TreeOfLife\Data\TreeOfLifeNodeData;
use App\TreeOfLife\Model\TreeOfLifeNode;

class TreeService implements TreeOfLifeServiceInterface
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @inheritDoc
     */
    public function getNode(int $id): ?TreeOfLifeNodeData
    {
        $query = <<<SQL
        SELECT
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence
        FROM tree_of_life_node tn
        WHERE tn.id = :id
        SQL;
        $row = $this->connection->execute($query, [':id' => $id])->fetch(\PDO::FETCH_ASSOC);

        return $row ? self::hydrateTreeNodeData($row) : null;
    }

    /**
     * @inheritDoc
     */
    public function getTree(): TreeOfLifeNode
    {
        // TODO: Implement getTree() method.
    }

    /**
     * @inheritDoc
     */
    public function getSubTree(int $id): TreeOfLifeNode
    {
        // TODO: Implement getSubTree() method.
    }

    /**
     * @inheritDoc
     */
    public function getNodePath(int $id): array
    {
        // TODO: Implement getNodePath() method.
    }

    /**
     * @inheritDoc
     */
    public function getParentNode(int $id): ?TreeOfLifeNodeData
    {
        // TODO: Implement getParentNode() method.
    }

    /**
     * @inheritDoc
     */
    public function getChildren(int $id): array
    {
        // TODO: Implement getChildren() method.
    }

    /**
     * @inheritDoc
     */
    public function saveTree(TreeOfLifeNode $root): void
    {
        // TODO: Implement saveTree() method.
    }

    /**
     * @inheritDoc
     */
    public function addNode(TreeOfLifeNodeData $node, int $parentId): void
    {
        // TODO: Implement addNode() method.
    }

    /**
     * @inheritDoc
     */
    public function moveSubTree(int $id, int $newParentId): void
    {
        // TODO: Implement moveSubTree() method.
    }

    /**
     * @inheritDoc
     */
    public function deleteSubTree(int $id): void
    {
        // TODO: Implement deleteSubTree() method.
    }

    /**
     * Преобразует один результат SQL-запроса в объект, представляющий узел дерева без связей с другими узлами.
     *
     * @param array<string,string|null> $row
     * @return TreeOfLifeNodeData
     */
    private static function hydrateTreeNodeData(array $row): TreeOfLifeNodeData
    {
        return new TreeOfLifeNodeData(
            (int)$row['id'],
            $row['name'],
            (bool)$row['extinct'],
            (int)$row['confidence']
        );
    }
}