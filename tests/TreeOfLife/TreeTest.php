<?php

namespace Tests\App\TreeOfLife;

use App\TreeOfLife\Data\TreeOfLifeNodeData;
use App\TreeOfLife\IO\TreeOfLifeLoader;
use App\TreeOfLife\Model\TreeOfLifeNode;
use App\TreeOfLife\Model\TreeOfLifeNodeDataInterface;
use App\TreeOfLife\Service\TreeOfLifeServiceInterface;
use App\TreeOfLife\Service\TreeService;
use Tests\App\Common\AbstractDatabaseTestCase;

class TreeTest extends AbstractDatabaseTestCase
{
    private const DATA_DIR = __DIR__ . '/../../data';
    private const CSV_NAME = 'treeoflife';
    private const NODES_CSV_PATH = self::DATA_DIR . '/' . self::CSV_NAME . '_nodes.csv';
    private const LINKS_CSV_PATH = self::DATA_DIR . '/' . self::CSV_NAME . '_links.csv';

    private TreeOfLifeServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()->execute('DELETE FROM tree_of_life');
        $this->getConnection()->execute('DELETE FROM tree_of_life_node');

        $this->service = new TreeService($this->getConnection());
    }

    public function testSaveAndLoadTree(): void
    {
        // Arrange
        $root = $this->loadTreeOfLifeFromCsv();
        $this->service->saveTree($root);

        // Act
        $root2 = $this->service->getTree();

        // Assert
        $this->assertEqualTrees($root, $root2);
    }

    public function testGetNode(): void
    {
        // Arrange
        $root = $this->loadTreeOfLifeFromCsv();
        $this->service->saveTree($root);

        // Act
        $rootNode = $this->service->getNode(1);
        $node = $this->service->getNode(2285);
        $leafNode = $this->service->getNode(65356);

        // Assert
        $this->assertTreeNode(new TreeOfLifeNodeData(1, 'Life on Earth', 0, 0), $rootNode);
        $this->assertTreeNode(new TreeOfLifeNodeData(2285, 'Aquificae', 0, 0), $node);
        $this->assertTreeNode(new TreeOfLifeNodeData(65356, 'Gorilla gorilla', 0, 0), $leafNode);
    }

    public function testGetDescendants(): void
    {
        // Arrange
        $root = $this->loadTreeOfLifeFromCsv();
        $this->service->saveTree($root);

        // Act
        $subTree = $this->service->getSubTree(14695);

        // Assert
        $this->assertTreeNode(new TreeOfLifeNodeData(14695, 'none', false, 0), $subTree);
        $this->assertTreeNode(new TreeOfLifeNodeData(14696, 'Pallenopsis', false, 0), $subTree->getChild(0));
        $this->assertTreeNode(new TreeOfLifeNodeData(14697, 'Callipallenidae', false, 0), $subTree->getChild(1));

        /*$this->assertTreeNode(new TreeOfLifeNodeData(2, 'Mammal', false, 0), $subTree);
        $this->assertTreeNode(new TreeOfLifeNodeData(4, 'Bird', false, 0), $subTree->getChild(0));
        $this->assertTreeNode(new TreeOfLifeNodeData(5, 'Rodent', false, 0), $subTree->getChild(1));*/

        // Act
        /*$children = $this->service->getChildren(2535);

        // Assert
        $this->assertCount(4, $children);
        $this->assertTreeNode(new TreeOfLifeNodeData(2536, 'Arachnida', false, 0), $children[0]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2537, 'Eurypterida', true, 0), $children[1]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2538, 'Xiphosura', false, 0), $children[2]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2539, 'Pycnogonida', false, 0), $children[3]);

        // Act
        $children = $this->service->getChildren(14697);

        // Assert
        $this->assertCount(0, $children);*/
    }

    private function assertTreeNode(TreeOfLifeNodeDataInterface $expected, TreeOfLifeNodeDataInterface $node): void
    {
        $this->assertEquals($expected->getId(), $node->getId());
        $this->assertEquals($expected->getName(), $node->getName());
        $this->assertEquals($expected->isExtinct(), $node->isExtinct());
        $this->assertEquals($expected->getConfidence(), $node->getConfidence());
    }

    private function assertEqualTrees(TreeOfLifeNode $expected, TreeOfLifeNode $root): void
    {
        $this->assertTreeNode($expected, $root);
        if ($expected->getParent())
        {
            $this->assertEquals($expected->getParent()->getId(), $root->getParent()->getId());
        }

        $expectedChildren = $expected->getChildren();
        $children = $root->getChildren();
        $this->assertCount(count($expectedChildren), $children);

        for ($i = 0, $iMax = count($expectedChildren); $i < $iMax; ++$i)
        {
            $this->assertEqualTrees($expectedChildren[$i], $children[$i]);
        }
    }

    private function loadTreeOfLifeFromCsv(): TreeOfLifeNode
    {
        $loader = new TreeOfLifeLoader();
        $loader->loadNodesCsv(self::NODES_CSV_PATH);
        $loader->loadLinksCsv(self::LINKS_CSV_PATH);
        return $loader->getTreeRoot();
    }
}