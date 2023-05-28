<?php
declare(strict_types=1);

namespace App\TreeOfLife\Service\AdjacencyList;

class AdjacencyListData
{
    private int $nodeId;
    private int $parentId;

    public function __construct(int $nodeId, int $parentId)
    {
        $this->nodeId = $nodeId;
        $this->parentId = $parentId;
    }

    public function getNodeId(): int
    {
        return $this->nodeId;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }
}
