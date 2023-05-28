<?php
declare(strict_types=1);

namespace App\TreeOfLife\Service\NestedSet;

use App\Common\Database\Connection;
use App\TreeOfLife\Data\TreeOfLifeNodeData;
use App\TreeOfLife\Model\TreeOfLifeNode;
use App\TreeOfLife\Model\TreeOfLifeNodeDataInterface;
use App\TreeOfLife\Service\TreeOfLifeServiceInterface;

class NestedSetTreeService implements TreeOfLifeServiceInterface
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
          ns.lft,
          ns.rgt,
          ns.depth,
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence
        FROM tree_of_life_nested_set ns
          INNER JOIN tree_of_life_node tn on ns.node_id = tn.id
        ORDER BY ns.lft
        SQL;
        $rows = $this->connection->execute($query)->fetchAll(\PDO::FETCH_ASSOC);

        return self::hydrateTree($rows);
    }

    public function getSubTree(int $id): TreeOfLifeNode
    {
        $query = <<<SQL
        SELECT
          ns.lft,
          ns.rgt,
          ns.depth,
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence
        FROM tree_of_life_nested_set root_ns
          INNER JOIN tree_of_life_nested_set ns ON ns.lft >= root_ns.lft AND ns.rgt <= root_ns.rgt
          INNER JOIN tree_of_life_node tn on ns.node_id = tn.id
        WHERE root_ns.node_id = :id
        ORDER BY ns.lft
        SQL;
        $rows = $this->connection->execute($query, [':id' => $id])->fetchAll(\PDO::FETCH_ASSOC);

        return self::hydrateTree($rows);
    }

    public function getNodePath(int $id): array
    {
        $query = <<<SQL
        SELECT
          tn.id,
          tn.name,
          tn.extinct,
          tn.confidence
        -- dns => descendant node
        FROM tree_of_life_nested_set dns
          -- ans => ancestor node
          INNER JOIN tree_of_life_nested_set ans ON (ans.lft <= dns.lft AND ans.rgt >= dns.rgt)
          INNER JOIN tree_of_life_node tn ON ans.node_id = tn.id
        WHERE dns.node_id = :id
        ORDER BY ans.lft DESC
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
        -- cns => child node
        FROM tree_of_life_nested_set cns
          -- pns => parent node
          INNER JOIN tree_of_life_nested_set pns ON (cns.lft > pns.lft AND cns.rgt < pns.rgt AND pns.depth = cns.depth - 1)
          INNER JOIN tree_of_life_node tn ON pns.node_id = tn.id
        WHERE cns.node_id = :id
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
        -- pns => parent node
        FROM tree_of_life_nested_set pns
          -- cns => child node
          INNER JOIN tree_of_life_nested_set cns ON (cns.lft > pns.lft AND cns.rgt < pns.rgt AND cns.depth = pns.depth + 1)
          INNER JOIN tree_of_life_node tn ON cns.node_id = tn.id
        WHERE pns.node_id = :id
        ORDER BY cns.lft
        SQL;

        $rows = $this->connection->execute($query, [':id' => $id])->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn(array $row) => self::hydrateTreeNodeData($row), $rows);
    }

    public function saveTree(TreeOfLifeNode $root): void
    {
        $allNodes = $root->listNodes();
        $allNestedSetData = self::buildNestedSetData($root);

        // Вместо записи всех узлов за один запрос делим массив на части.
        /** @var TreeOfLifeNode[] $nodes */
        foreach (array_chunk($allNodes, self::INSERT_BATCH_SIZE) as $nodes)
        {
            $this->insertIntoNodeTable($nodes);
        }

        /** @var NestedSetData[] $nestedSetData */
        foreach (array_chunk($allNestedSetData, self::INSERT_BATCH_SIZE) as $nestedSetData)
        {
            $this->insertIntoTreeTable($nestedSetData);
        }
    }

    /**
     * @param TreeOfLifeNodeData $node
     * @param int $parentId
     * @return void
     * @throws \Throwable
     */
    public function addNode(TreeOfLifeNodeData $node, int $parentId): void
    {
        $this->doWithTransaction(function () use ($node, $parentId) {
            $this->insertIntoNodeTable([$node]);
            $this->connection->execute('CALL tree_of_life_nested_set_add_node(?, ?)', [$node->getId(), $parentId]);
        });
    }

    public function moveSubTree(int $id, int $newParentId): void
    {
        $this->doWithTransaction(function () use ($id, $newParentId) {
            $this->connection->execute('CALL tree_of_life_nested_set_delete_move_node(?, ?)', [$id, $newParentId]);
        });
    }

    public function deleteSubTree(int $id): void
    {
        $this->doWithTransaction(function () use ($id) {
            $this->connection->execute('CALL tree_of_life_nested_set_delete_sub_tree(?)', [$id]);
        });
    }

    public function validateNestedSetData(): void
    {
        $root = $this->getTree();
        $expectedNestedSetData = self::buildNestedSetData($root);

        $query = <<<SQL
            SELECT node_id, lft, rgt, depth
            FROM tree_of_life_nested_set
            ORDER BY lft
        SQL;
        $rows = $this->connection->execute($query)->fetchAll(\PDO::FETCH_ASSOC);
        $nestedSetData = array_map($this->hydrateNestedSetData(...), $rows);

        /**
         * @var NestedSetData[] $nestedSetDataMap - отображает ID узла на данные Nested Set.
         */
        $nestedSetDataMap = array_combine(
            array_map(static fn(NestedSetData $d) => $d->getNodeId(), $nestedSetData),
            $nestedSetData
        );

        $errors = [];
        foreach ($expectedNestedSetData as $expected)
        {
            $nodeId = $expected->getNodeId();
            $got = $nestedSetDataMap[$nodeId] ?? throw new \LogicException("Cannot find Nested Set node {$nodeId}");
            if ($got->getLeft() !== $expected->getLeft())
            {
                $errors[] = "Invalid lft={$got->getLeft()} in node $nodeId, expected lft={$expected->getLeft()}";
            }
            if ($got->getRight() !== $expected->getRight())
            {
                $errors[] = "Invalid rgt={$got->getRight()} in node $nodeId, expected rgt={$expected->getRight()}";
            }
            if ($got->getDepth() !== $expected->getDepth())
            {
                $errors[] = "Invalid depth={$got->getDepth()} in node $nodeId, expected depth={$expected->getDepth()}";
            }
        }
        if (count($errors) > 0)
        {
            throw new \RuntimeException("Inconsistent Nested Set data:\n" . implode("\n", $errors));
        }
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
     * @param NestedSetData[] $nodes
     * @return void
     */
    private function insertIntoTreeTable(array $nodes): void
    {
        if (count($nodes) === 0)
        {
            return;
        }

        $placeholders = self::buildInsertPlaceholders(count($nodes), 4);
        $query = <<<SQL
            INSERT INTO tree_of_life_nested_set (node_id, lft, rgt, depth)
            VALUES $placeholders
            SQL;
        $params = [];
        foreach ($nodes as $node)
        {
            $params[] = $node->getNodeId();
            $params[] = $node->getLeft();
            $params[] = $node->getRight();
            $params[] = $node->getDepth();
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
     * @param TreeOfLifeNode $root
     * @return NestedSetData[]
     */
    private static function buildNestedSetData(TreeOfLifeNode $root): array
    {
        $results = [];
        $counter = 0;
        self::addNestedSetDataRecursive($root, 1, $results, $counter);

        return $results;
    }

    /**
     * @param TreeOfLifeNode $node
     * @param int $depth
     * @param NestedSetData[] $results
     * @param int $counter
     * @return void
     */
    private static function addNestedSetDataRecursive(TreeOfLifeNode $node, int $depth, array &$results, int &$counter): void
    {
        $left = ++$counter;
        foreach ($node->getChildren() as $child)
        {
            self::addNestedSetDataRecursive($child, $depth + 1, $results, $counter);
        }
        $right = ++$counter;
        $results[] = new NestedSetData($node->getId(), $left, $right, $depth);
    }

    /**
     * Преобразует набор результатов SQL-запроса в дерево с одним корнем.
     *
     * @param array<array<string,string|null>> $rows
     * @return TreeOfLifeNode
     */
    private static function hydrateTree(array $rows): TreeOfLifeNode
    {
        if (count($rows) === 0)
        {
            throw new \InvalidArgumentException('Cannot create tree from empty result set');
        }

        /**
         * @var array<array{0:TreeOfLifeNode,1:int}> $parentStack - элемент стека является парой (node, right).
         * В языке PHP пару значений можно представить массивом из двух элементов.
         */
        $parentStack = [];
        foreach ($rows as $row)
        {
            $node = self::hydrateTreeNode($row);
            $right = (int)$row['rgt'];

            // Подбираем родителя текущего узла: это первый узел в стеке, для которого node.right < parent.right
            while (count($parentStack) > 0)
            {
                [$parent, $parentRight] = $parentStack[count($parentStack) - 1];
                if ($right > $parentRight)
                {
                    // Выбрасываем несостоявшегося родителя из стека.
                    array_pop($parentStack);
                }
                else
                {
                    // Добавляем узел к подтверждённому родителю.
                    $parent->addChildUnsafe($node);
                    break;
                }
            }

            $parentStack[] = [$node, $right];
        }

        // Первый элемент в стеке - это пара, содержащая корень дерева.
        return $parentStack[0][0];
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

    /**
     * Преобразует один результат SQL-запроса в объект, представляющий данные узла в Nested Set.
     *
     * @param array<string,string|null> $row
     * @return NestedSetData
     */
    private static function hydrateNestedSetData(array $row): NestedSetData
    {
        return new NestedSetData(
            (int)$row['node_id'],
            (int)$row['lft'],
            (int)$row['rgt'],
            (int)$row['depth']
        );
    }
}
