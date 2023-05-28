<?php
declare(strict_types=1);

namespace App\TreeOfLife\Service\NestedSet;

class NestedSetData
{
    private int $nodeId;
    private int $left;
    private int $right;
    private int $depth;

    public function __construct(int $nodeId, int $left, int $right, int $depth)
    {
        $this->nodeId = $nodeId;
        $this->left = $left;
        $this->right = $right;
        $this->depth = $depth;
    }

    public function getNodeId(): int
    {
        return $this->nodeId;
    }

    public function getLeft(): int
    {
        return $this->left;
    }

    public function getRight(): int
    {
        return $this->right;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }
}
