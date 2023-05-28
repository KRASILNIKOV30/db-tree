<?php

declare(strict_types=1);

namespace App\TreeOfLife\IO;

use App\TreeOfLife\Model\TreeOfLifeNode;
use Generator;
use LogicException;
use RuntimeException;

class TreeOfLifeLoader
{
    private const NODES_CSV_HEADER = [
        'node_id', 'node_name', 'child_nodes', 'leaf_node', 'tolorg_link', 'extinct', 'confidence', 'phylesis'
    ];

    private array $nodes = [];
    private ?TreeOfLifeNode $treeRoot;

    public function loadNodesCsv(string $csvPath): void
    {
        foreach ($this->iterateCsvFileRows($csvPath) as $row)
        {
            $node = new TreeOfLifeNode((int)$row['node_id'], $row['node_name'], (bool)$row['extinct'], (int)$row['confidence']);
            $this->nodes[$node->getId()] = $node;
        }

        if (count($this->nodes) === 0)
        {
            throw new RuntimeException("Tree at path '$csvPath' is empty");
        }
    }

    public function loadLinksCsv(string $csvPath): void
    {
        foreach ($this->iterateCsvFileRows($csvPath) as $row)
        {
            $parentId = (int)$row['source_node_id'];
            $childId = (int)$row['target_node_id'];
            $parent = $this->getNode($parentId);
            $child = $this->getNode($childId);
            $parent->addChild($child);
        }

        $roots = array_values(array_filter($this->nodes, static fn(TreeOfLifeNode $node) => !$node->getParent()));

        // Проверка на наличие множества корневых узлов.
        // Проверять на отсутствие корней не требуется, потому что алгоритм загрузки исключает подобную ситуацию.
        if (count($roots) > 1)
        {
            $rootIds = array_map(static fn(TreeOfLifeNode $node) => $node->getId(), $roots);
            throw new RuntimeException('Tree has multiple roots: ' . implode(', ', $rootIds));
        }

        $this->treeRoot = $roots[0];
    }

    public function getTreeRoot(): TreeOfLifeNode
    {
        if (!$this->treeRoot)
        {
            throw new LogicException("Tree root is not initialized yet");
        }
        return $this->treeRoot;
    }

    /**
     * Обходит все строки CSV файла, кроме первой, и возвращает каждую строку как ассоциативный массив.
     * Для каждой возвращённой строки, ключами становятся колонки из заголовка CSV-файла, а значениями - колонки данной строки.
     *
     * @param string $csvPath - путь к файлу
     * @return Generator<array>
     */
    private function iterateCsvFileRows(string $csvPath): Generator
    {
        $csv = @fopen($csvPath, 'rb');
        if (!$csv)
        {
            throw new RuntimeException("Failed to open '$csvPath' for reading");
        }
        try
        {
            $headerRow = @fgetcsv($csv);
            if (!$headerRow)
            {
                throw new RuntimeException("CSV file '$csvPath' has no header row");
            }

            while (($row = @fgetcsv($csv)) !== false)
            {
                yield array_combine($headerRow, $row);
            }
        }
        finally
        {
            @fclose($csv);
        }
    }

    private function getNode(int $id): TreeOfLifeNode
    {
        $node = $this->nodes[$id];
        if (!$node)
        {
            throw new RuntimeException("Cannot find node with id $id");
        }
        return $node;
    }
}
