<?php
declare(strict_types=1);

namespace App\TreeOfLife\Model;

interface TreeOfLifeNodeDataInterface
{
    public function getId(): int;

    public function getName(): string;

    public function isExtinct(): bool;

    public function getConfidence(): int;
}
