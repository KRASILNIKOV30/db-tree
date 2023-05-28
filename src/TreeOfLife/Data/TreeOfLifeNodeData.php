<?php
declare(strict_types=1);

namespace App\TreeOfLife\Data;

use App\TreeOfLife\Model\TreeOfLifeNodeDataInterface;

final class TreeOfLifeNodeData implements TreeOfLifeNodeDataInterface
{
    private int $id;
    private string $name;
    private bool $extinct;
    private int $confidence;

    /**
     * @param int $id - ID узла дерева жизни
     * @param string $name - название узла дерева жизни
     * @param bool $extinct - признак вымершего вида
     * @param int $confidence - степень уверенности в правильном местоположении вида (или иного узла) в заданном месте в дереве жизни
     */
    public function __construct(int $id, string $name, bool $extinct, int $confidence)
    {
        $this->id = $id;
        $this->name = $name;
        $this->extinct = $extinct;
        $this->confidence = $confidence;
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
}
