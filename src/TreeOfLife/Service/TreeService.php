<?php

namespace App\TreeOfLife\Service;

use App\Common\Database\Connection;
use App\TreeOfLife\Data\TreeOfLifeNodeData;
use App\TreeOfLife\Model\TreeOfLifeNode;

class TreeService implements TreeOfLifeServiceInterface
{
    private const INSERT_BATCH_SIZE = 1000;
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
        $query = <<<SQL
        SELECT
          t.path,
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence
        FROM tree_of_life t
          INNER JOIN tree_of_life_node tn on t.node_id = tn.id
        ORDER BY tn.id
        SQL;
        $rows = $this->connection->execute($query)->fetchAll(\PDO::FETCH_ASSOC);

        return $this->hydrateTree($rows);
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
        $allNodes = $root->listNodes();
        $allTreeData = self::buildTreeData($root);

        // Вместо записи всех узлов за один запрос делим массив на части.
        /** @var TreeOfLifeNode[] $nodes */
        foreach (array_chunk($allNodes, self::INSERT_BATCH_SIZE) as $nodes)
        {
            $this->insertIntoNodeTable($nodes);
        }

        /** @var TreeData[] $treeData */
        foreach (array_chunk($allTreeData, self::INSERT_BATCH_SIZE) as $treeData)
        {
            $this->insertIntoTreeTable($treeData);
        }
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
     * @param TreeOfLifeNode $root
     * @return TreeData[]
     */
    private static function buildTreeData(TreeOfLifeNode $root): array
    {
        $results = [];
        self::addDataRecursive($root, '', $results);

        return $results;
    }

    private static function addDataRecursive(TreeOfLifeNode $node, string $parentPath, array &$results)
    {
        if (empty($parentPath)) {
            $path = (string)$node->getId();
        } else {
            $path = $parentPath . '/' . (string)$node->getId();
        }
        $results[] = new TreeData($node->getId(), $path);
        foreach ($node->getChildren() as $child)
        {
            self::addDataRecursive($child, $path, $results);
        }
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

    private function doWithTransaction(callable $action): void
    {
        $this->connection->beginTransaction();
        $commit = false;
        try
        {
            $action();
            $commit = true;
        }
        finally
        {
            if ($commit)
            {
                $this->connection->commit();
            }
            else
            {
                $this->connection->rollback();
            }
        }
    }

    private function insertIntoNodeTable(array $nodes): void
    {
        $placeholders = self::buildInsertPlaceholders(count($nodes), 4);
        $query = <<<SQL
            INSERT INTO tree_of_life_node (id, name, extinct, confidence)
            VALUES $placeholders
            SQL;
        $params = [];
        foreach ($nodes as $node)
        {
            $params[] = $node->getId();
            $params[] = $node->getName();
            $params[] = (int)$node->isExtinct();
            $params[] = $node->getConfidence();
        }
        $this->connection->execute($query, $params);
    }

    /**
     * @param TreeData[] $nodes
     * @return void
     */
    private function insertIntoTreeTable(array $nodes): void
    {
        if (count($nodes) === 0)
        {
            return;
        }

        $placeholders = self::buildInsertPlaceholders(count($nodes), 2);
        $query = <<<SQL
            INSERT INTO tree_of_life (node_id, path)
            VALUES $placeholders
            SQL;
        $params = [];
        foreach ($nodes as $node)
        {
            $params[] = $node->getNodeId();
            $params[] = $node->getPath();
        }
        $this->connection->execute($query, $params);
    }

    private static function buildInsertPlaceholders(int $rowCount, int $columnCount): string
    {
        if ($rowCount <= 0 || $columnCount <= 0)
        {
            throw new \InvalidArgumentException("Invalid row count $rowCount or column count $columnCount");
        }

        $rowPlaceholders = '(' . str_repeat('?, ', $columnCount - 1) . '?)';
        $placeholders = str_repeat("$rowPlaceholders, ", $rowCount - 1) . $rowPlaceholders;

        return $placeholders;
    }

    /**
     * @param array<array<string,string|null>> $rows
     * @return TreeOfLifeNode
     */
    private function hydrateTree(array $rows): TreeOfLifeNode
    {
        $nodesMap = self::hydrateNodesMap($rows);

        $root = null;
        foreach ($rows as $row)
        {
            $id = (int)$row['id'];
            if ($parentId = $this->getParentId($row))
            {
                $node = $nodesMap[$id];
                $parent = $nodesMap[$parentId];
                $parent->addChildUnsafe($node);
            }
            else
            {
                $root = $nodesMap[$id];
            }
        }
        return $root;
    }

    /**
     * @param array<string> $row
     * @return int|null
     */
    private function getParentId(array $row): ?int
    {
        $stringPath = $row['path'];
        $path = explode('/', $stringPath);
        $pathLen = count($path);
        if ($pathLen < 2) {
            return null;
        }
        return (int)$path[$pathLen - 2];
    }

    /**
     * Преобразует набор результатов SQL-запроса в словарь, где ключи - ID узлов, а значения - объекты.
     *
     * @param array<array<string,string|null>> $rows
     * @return TreeOfLifeNode[] - отображает ID узла на узел.
     */
    private static function hydrateNodesMap(array $rows): array
    {
        $nodes = [];
        foreach ($rows as $row)
        {
            $node = self::hydrateTreeNode($row);
            $nodes[$node->getId()] = $node;
        }
        return $nodes;
    }

    /**
     * Преобразует один результат SQL-запроса в объект, представляющий узел дерева.
     *
     * @param array<string,string|null> $row
     * @return TreeOfLifeNode
     */
    private static function hydrateTreeNode(array $row): TreeOfLifeNode
    {
        return new TreeOfLifeNode(
            (int)$row['id'],
            $row['name'],
            (bool)$row['extinct'],
            (int)$row['confidence']
        );
    }
}