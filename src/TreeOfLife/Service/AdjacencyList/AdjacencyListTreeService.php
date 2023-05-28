<?php
declare(strict_types=1);

namespace App\TreeOfLife\Service\AdjacencyList;

use App\Common\Database\Connection;
use App\TreeOfLife\Data\TreeOfLifeNodeData;
use App\TreeOfLife\Model\TreeOfLifeNode;
use App\TreeOfLife\Model\TreeOfLifeNodeDataInterface;
use App\TreeOfLife\Service\TreeOfLifeServiceInterface;

class AdjacencyListTreeService implements TreeOfLifeServiceInterface
{
    private const INSERT_BATCH_SIZE = 1000;

    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

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


    public function getTree(): TreeOfLifeNode
    {
        $query = <<<SQL
        SELECT
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence,
          t.parent_id
        FROM tree_of_life_node tn
          LEFT JOIN tree_of_life_adjacency_list t ON t.node_id = tn.id
        SQL;

        $rows = $this->connection->execute($query)->fetchAll(\PDO::FETCH_ASSOC);

        return self::hydrateTree($rows);
    }

    public function getSubTree(int $id): TreeOfLifeNode
    {
        $query = <<<SQL
        WITH RECURSIVE cte AS
          (
            SELECT
              t.node_id,
              0 AS parent_id
            FROM tree_of_life_adjacency_list t
            WHERE t.node_id = :id
            UNION ALL
            SELECT
              t.node_id,
              t.parent_id
            FROM tree_of_life_adjacency_list t
              INNER JOIN cte ON t.parent_id = cte.node_id
          )
        SELECT
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence,
          cte.parent_id
        FROM cte
          INNER JOIN tree_of_life_node tn ON tn.id = cte.node_id
        SQL;
        $rows = $this->connection->execute($query, [':id' => $id])->fetchAll(\PDO::FETCH_ASSOC);

        return self::hydrateTree($rows);
    }

    public function getNodePath(int $id): array
    {
        $query = <<<SQL
        WITH RECURSIVE cte AS
          (
            SELECT
              t.node_id,
              t.parent_id
            FROM tree_of_life_node n
              LEFT JOIN tree_of_life_adjacency_list t on t.node_id = n.id
            WHERE
              n.id = :id
            UNION ALL
            SELECT
              n.id,
              t.parent_id
            FROM tree_of_life_node n
              INNER JOIN cte ON n.id = cte.parent_id
              LEFT JOIN tree_of_life_adjacency_list t on t.node_id = n.id
          )
        SELECT
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence
        FROM cte
          INNER JOIN tree_of_life_node tn ON tn.id = cte.node_id
        SQL;
        $rows = $this->connection->execute($query, [':id' => $id])->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn(array $row) => self::hydrateTreeNodeData($row), $rows);
    }

    public function getParentNode(int $id): ?TreeOfLifeNodeData
    {
        $query = <<<SQL
        SELECT
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence
        FROM tree_of_life_node tn
          INNER JOIN tree_of_life_adjacency_list t on tn.id = t.parent_id
        WHERE t.node_id = :id
        SQL;
        $row = $this->connection->execute($query, [':id' => $id])->fetch(\PDO::FETCH_ASSOC);

        return $row ? self::hydrateTreeNodeData($row) : null;
    }

    public function getChildren(int $id): array
    {
        $query = <<<SQL
        SELECT
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence
        FROM tree_of_life_node tn
          INNER JOIN tree_of_life_adjacency_list t on tn.id = t.node_id
        WHERE t.parent_id = :id
        SQL;
        $rows = $this->connection->execute($query, [':id' => $id])->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn(array $row) => self::hydrateTreeNodeData($row), $rows);
    }

    public function saveTree(TreeOfLifeNode $root): void
    {
        $allNodes = $root->listNodes();

        // Вместо записи всех узлов за один запрос делим массив на части.
        /** @var TreeOfLifeNode[] $nodes */
        foreach (array_chunk($allNodes, self::INSERT_BATCH_SIZE) as $nodes)
        {
            $this->doWithTransaction(function () use ($nodes) {
                $this->insertIntoNodeTable($nodes);
                $this->insertIntoTreeTable($this->transformToAdjacencyListData($nodes));
            });
        }
    }

    public function addNode(TreeOfLifeNodeData $node, int $parentId): void
    {
        $this->doWithTransaction(function () use ($node, $parentId) {
            $this->insertIntoNodeTable([$node]);
            $this->insertIntoTreeTable([new AdjacencyListData($node->getId(), $parentId)]);
        });
    }

    public function moveSubTree(int $id, int $newParentId): void
    {
        // Проверяем, что новый родитель является потомком узла или тем же узлом.
        $newParentPath = $this->getNodePath($newParentId);
        foreach ($newParentPath as $newParentAncestor)
        {
            if ($newParentAncestor->getId() === $id)
            {
                throw new \InvalidArgumentException("Cannot move node $id into descendant node $newParentId");
            }
        }

        $query = <<<SQL
        UPDATE tree_of_life_adjacency_list
        SET
          parent_id = :new_parent_id
        WHERE node_id = :id
        SQL;
        $params = [
            ':id' => $id,
            ':new_parent_id' => $newParentId,
        ];

        $this->connection->execute($query, $params);
    }

    public function deleteSubTree(int $id): void
    {
        // Удаляем рекурсивным запросом всё поддерево заданного узла.
        // Удаляются только строки из tree_of_life_node, а строки из tree_of_life_adjacency_list будут удалены
        //   за счёт ON DELETE CASCADE у внешнего ключа
        $query = <<<SQL
        WITH RECURSIVE cte AS
          (
            SELECT
              t.node_id,
              0 AS parent_id
            FROM tree_of_life_adjacency_list t
            WHERE t.node_id = :id
            UNION ALL
            SELECT
              t.node_id,
              t.parent_id
            FROM tree_of_life_adjacency_list t
              INNER JOIN cte ON t.parent_id = cte.node_id
          )
        DELETE tn
        FROM tree_of_life_node tn
          INNER JOIN cte ON tn.id = cte.node_id
        SQL;
        $this->connection->execute($query, [':id' => $id]);
    }

    /**
     * @param callable $action
     * @return void
     */
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

    /**
     * @param TreeOfLifeNode[] $nodes
     * @return AdjacencyListData[]
     */
    private function transformToAdjacencyListData(array $nodes): array
    {
        $results = [];
        foreach ($nodes as $node)
        {
            $parent = $node->getParent();
            if ($parent !== null)
            {
                $results[] = new AdjacencyListData($node->getId(), $parent->getId());
            }
        }

        return $results;
    }

    /**
     * Записывает узлы в таблицу с информацией об узлах.
     *
     * @param TreeOfLifeNodeDataInterface[] $nodes
     * @return void
     */
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
     * Записывает узлы в таблицу с информацией о структуре дерева
     *
     * @param AdjacencyListData[] $nodes
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
            INSERT INTO tree_of_life_adjacency_list (node_id, parent_id)
            VALUES $placeholders
            SQL;
        $params = [];
        foreach ($nodes as $node)
        {
            $params[] = $node->getNodeId();
            $params[] = $node->getParentId();
        }
        $this->connection->execute($query, $params);
    }

    /**
     * Генерирует строку с SQL-заполнителями для множественной записи через INSERT.
     * Результат может выглядеть так: "(?, ?), (?, ?), (?, ?)"
     *
     * @param int $rowCount
     * @param int $columnCount
     * @return string
     */
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
     * Преобразует набор результатов SQL-запроса в дерево с одним корнем.
     * Метод предполагает, что в наборе результатов есть ровно один результат с parent_id=null.
     *
     * @param array<array<string,string|null>> $rows
     * @return TreeOfLifeNode
     */
    private static function hydrateTree(array $rows): TreeOfLifeNode
    {
        $nodesMap = self::hydrateNodesMap($rows);

        $root = null;
        foreach ($rows as $row)
        {
            $id = (int)$row['id'];
            if ($parentId = (int)$row['parent_id'])
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
