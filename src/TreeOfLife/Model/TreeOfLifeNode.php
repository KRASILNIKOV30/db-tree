<?php
declare(strict_types=1);

namespace App\TreeOfLife\Model;

final class TreeOfLifeNode implements TreeOfLifeNodeDataInterface
{
    private int $id;
    private string $name;
    private bool $extinct;
    private int $confidence;

    private ?TreeOfLifeNode $parent;
    /** @var TreeOfLifeNode[] */
    private array $children;

    /**
     * @param int $id - ID узла дерева жизни
     * @param string $name - название узла дерева жизни
     * @param bool $extinct - признак вымершего вида
     * @param int $confidence - степень уверенности в правильном местоположении вида (или иного узла) в заданном месте в дереве жизни
     * @param TreeOfLifeNode|null $parent
     * @param TreeOfLifeNode[] $children
     */
    public function __construct(int $id, string $name, bool $extinct, int $confidence, ?TreeOfLifeNode $parent = null, array $children = [])
    {
        $this->id = $id;
        $this->name = $name;
        $this->extinct = $extinct;
        $this->confidence = $confidence;
        $this->parent = $parent;
        $this->children = $children;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isExtinct(): bool
    {
        return $this->extinct;
    }

    public function getConfidence(): int
    {
        return $this->confidence;
    }

    public function getParent(): ?TreeOfLifeNode
    {
        return $this->parent;
    }

    public function getChild(int $index): TreeOfLifeNode
    {
        $child = $this->children[$index] ?? null;
        if (!$child)
        {
            throw new \OutOfBoundsException("No child with index $index");
        }
        return $child;
    }

    /**
     * @return TreeOfLifeNode[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Безопасное добавление дочернего узла в дерево.
     * Выполняет проверки целостности.
     *
     * @param TreeOfLifeNode $child
     * @return void
     */
    public function addChild(TreeOfLifeNode $child): void
    {
        // Проверка на попытку установить множество родительских узлов.
        if ($child->parent)
        {
            if ($child->parent === $this)
            {
                return;
            }
            throw new \RuntimeException("Cannot add child node {$child->getId()}: this node already has parent");
        }
        // Проверка на циклические зависимости и на попытку установки узла родителем самого себя.
        for ($ancestor = $this; $ancestor !== null; $ancestor = $ancestor->parent)
        {
            if ($ancestor === $child)
            {
                throw new \RuntimeException("Cannot add node {$child->getId()} as child of {$this->getId()}: cyclic dependencies not allowed");
            }
        }

        $this->addChildUnsafe($child);
    }

    /**
     * Небезопасное добавление дочернего узла в дерево.
     * Не выполняет никаких проверок целостности.
     *
     * @param TreeOfLifeNode $child
     * @return void
     */
    public function addChildUnsafe(TreeOfLifeNode $child): void
    {
        $this->children[] = $child;
        $child->parent = $this;
    }

    /**
     * Возвращает узлы дерева или поддерева в виде списка, полученного в результате обхода в глубину
     *
     * @return TreeOfLifeNode[]
     */
    public function listNodes(): array
    {
        $nodes = [];
        $this->walk(function (TreeOfLifeNode $node) use (&$nodes) {
            $nodes[] = $node;
        });
        return $nodes;
    }

    /**
     * Обходит узлы дерева или поддерева в глубину, начиная с заданного узла.
     *
     * @param callable $callback
     * @return void
     */
    public function walk(callable $callback): void
    {
        $callback($this);
        foreach ($this->children as $child)
        {
            $child->walk($callback);
        }
    }
}
