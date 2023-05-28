<?php

namespace App\TreeOfLife\Service;

class TreeData
{
    private int $nodeId;
    private string $path;

    public function __constructor(int $nodeId, string $path): void
    {
       $this->nodeId = $nodeId;
       $this->path = $path;
    }

    public function getNodeId(): int
    {
        return $this->nodeId;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}