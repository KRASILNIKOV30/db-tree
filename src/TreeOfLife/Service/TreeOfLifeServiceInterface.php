<?php
declare(strict_types=1);

namespace App\TreeOfLife\Service;

use App\TreeOfLife\Data\TreeOfLifeNodeData;
use App\TreeOfLife\Model\TreeOfLifeNode;

interface TreeOfLifeServiceInterface
{
    /**
     * Возвращает данные отдельного узла
     *
     * @param int $id
     * @return TreeOfLifeNodeData|null
     */
    public function getNode(int $id): ?TreeOfLifeNodeData;

    /**
     * Возвращает всё дерево целиком
     *
     * @return TreeOfLifeNode
     */
    public function getTree(): TreeOfLifeNode;

    /**
     * Возвращает ветвь дерева для указанного узла (т.е. поддерево данного узла)
     *
     * @param int $id
     * @return TreeOfLifeNode
     */
    public function getSubTree(int $id): TreeOfLifeNode;

    /**
     * Возвращает путь к узлу, т.е. последовательность всех его предков, начиная с корня.
     *
     * @param int $id
     * @return TreeOfLifeNodeData[]
     */
    public function getNodePath(int $id): array;

    /**
     * Возвращает родителя узла, т.е. его ближайшего предка
     *
     * @param int $id
     * @return TreeOfLifeNodeData|null
     */
    public function getParentNode(int $id): ?TreeOfLifeNodeData;

    /**
     * Возвращает список дочерних узлов к узлу, т.е ближайших потомков
     *
     * @param int $id
     * @return TreeOfLifeNodeData[]
     */
    public function getChildren(int $id): array;

    /**
     * Сохраняет всё дерево целиком.
     *
     * @param TreeOfLifeNode $root
     * @return void
     */
    public function saveTree(TreeOfLifeNode $root): void;

    /**
     * Добавляет узел к дереву.
     *
     * @param TreeOfLifeNodeData $node
     * @param int $parentId
     * @return void
     * @throws \InvalidArgumentException
     */
    public function addNode(TreeOfLifeNodeData $node, int $parentId): void;

    /**
     * Перемещает узел к новому родителю.
     * При попытке сделать узел дочерним для самого себя или своих потомков бросается исключение.
     *
     * @param int $id
     * @param int $newParentId
     * @return void
     * @throws \InvalidArgumentException
     */
    public function moveSubTree(int $id, int $newParentId): void;

    /**
     * Удаляет узел и всех его потомков.
     *
     * @param int $id
     * @return void
     * @throws \LogicException
     */
    public function deleteSubTree(int $id): void;
}
