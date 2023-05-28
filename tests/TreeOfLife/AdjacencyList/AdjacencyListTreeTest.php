<?php
declare(strict_types=1);

namespace Tests\App\TreeOfLife\AdjacencyList;

use App\TreeOfLife\Data\TreeOfLifeNodeData;
use App\TreeOfLife\IO\TreeOfLifeLoader;
use App\TreeOfLife\Model\TreeOfLifeNode;
use App\TreeOfLife\Model\TreeOfLifeNodeDataInterface;
use App\TreeOfLife\Service\AdjacencyList\AdjacencyListTreeService;
use App\TreeOfLife\Service\TreeOfLifeServiceInterface;
use Tests\App\Common\AbstractDatabaseTestCase;

class AdjacencyListTreeTest extends AbstractDatabaseTestCase
{
    private const DATA_DIR = __DIR__ . '/../../../data';
    private const NODES_CSV_PATH = self::DATA_DIR . '/treeoflife_nodes.csv';
    private const LINKS_CSV_PATH = self::DATA_DIR . '/treeoflife_links.csv';

    private TreeOfLifeServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->getConnection()->execute('DELETE FROM tree_of_life_adjacency_list');
        $this->getConnection()->execute('DELETE FROM tree_of_life_node');

        $this->service = new AdjacencyListTreeService($this->getConnection());
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

        // Act
        $children = $this->service->getChildren(2535);

        // Assert
        $this->assertCount(4, $children);
        $this->assertTreeNode(new TreeOfLifeNodeData(2536, 'Arachnida', false, 0), $children[0]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2537, 'Eurypterida', true, 0), $children[1]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2538, 'Xiphosura', false, 0), $children[2]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2539, 'Pycnogonida', false, 0), $children[3]);

        // Act
        $children = $this->service->getChildren(14697);

        // Assert
        $this->assertCount(0, $children);
    }

    public function testGetAncestors(): void
    {
        // Arrange
        $root = $this->loadTreeOfLifeFromCsv();
        $this->service->saveTree($root);

        // Act
        $path = $this->service->getNodePath(14697);

        // Assert
        $this->assertCount(14, $path);
        $this->assertTreeNode(new TreeOfLifeNodeData(14697, 'Callipallenidae', false, 0), $path[0]);
        $this->assertTreeNode(new TreeOfLifeNodeData(14695, 'none', false, 0), $path[1]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2539, 'Pycnogonida', false, 0), $path[2]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2535, 'Chelicerata', false, 0), $path[3]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2469, 'Arthropoda', false, 0), $path[4]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2468, 'none', false, 0), $path[5]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2467, 'Ecdysozoa', false, 0), $path[6]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2459, 'Bilateria', false, 0), $path[7]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2458, 'none', false, 0), $path[8]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2374, 'Animals', false, 0), $path[9]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2373, 'none', false, 0), $path[10]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2372, 'Opisthokonts', false, 0), $path[11]);
        $this->assertTreeNode(new TreeOfLifeNodeData(3, 'Eukaryotes', false, 0), $path[12]);
        $this->assertTreeNode(new TreeOfLifeNodeData(1, 'Life on Earth', false, 0), $path[13]);

        // Act
        $parentNode = $this->service->getParentNode(2539);
        // Assert
        $this->assertTreeNode(new TreeOfLifeNodeData(2535, 'Chelicerata', false, 0), $parentNode);

        // Act
        $parentNode = $this->service->getParentNode(2535);

        // Assert
        $this->assertTreeNode(new TreeOfLifeNodeData(2469, 'Arthropoda', false, 0), $parentNode);
    }

    public function testAddNode(): void
    {
        // Arrange
        $root = $this->loadTreeOfLifeFromCsv();
        $this->service->saveTree($root);

        // Act
        $this->service->addNode(new TreeOfLifeNodeData(80000, 'Fantastic Beasts', true, 0), 1);
        $this->service->addNode(new TreeOfLifeNodeData(80001, 'Chimera', true, 0), 80000);
        $this->service->addNode(new TreeOfLifeNodeData(80002, 'Dragon', true, 0), 80000);
        $this->service->addNode(new TreeOfLifeNodeData(80003, 'Manticore', true, 0), 80000);

        // Assert
        $subTree = $this->service->getSubTree(80000);
        $this->assertTreeNode(new TreeOfLifeNodeData(80000, 'Fantastic Beasts', true, 0), $subTree);
        $this->assertTreeNode(new TreeOfLifeNodeData(80001, 'Chimera', true, 0), $subTree->getChild(0));
        $this->assertTreeNode(new TreeOfLifeNodeData(80002, 'Dragon', true, 0), $subTree->getChild(1));
        $this->assertTreeNode(new TreeOfLifeNodeData(80003, 'Manticore', true, 0), $subTree->getChild(2));

        $parent = $this->service->getParentNode(80000);
        $this->assertTreeNode(new TreeOfLifeNodeData(1, 'Life on Earth', false, 0), $parent);
    }

    public function testMoveNode(): void
    {
        // Arrange
        $root = $this->loadTreeOfLifeFromCsv();
        $this->service->saveTree($root);

        // Pre-assert
        $parent = $this->service->getParentNode(14697);
        $this->assertTreeNode(new TreeOfLifeNodeData(14695, 'none', false, 0), $parent);

        // Act - move Callipallenidae to Eurypterida
        $this->service->moveSubTree(14697, 2537);

        // Assert
        $parent = $this->service->getParentNode(14697);
        $this->assertTreeNode(new TreeOfLifeNodeData(2537, 'Eurypterida', true, 0), $parent);
    }

    public function testDeleteSubTree(): void
    {
        // Arrange
        $root = $this->loadTreeOfLifeFromCsv();
        $this->service->saveTree($root);

        // Pre-assert
        $children = $this->service->getChildren(2535);
        $this->assertCount(4, $children);
        $this->assertTreeNode(new TreeOfLifeNodeData(2536, 'Arachnida', false, 0), $children[0]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2537, 'Eurypterida', true, 0), $children[1]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2538, 'Xiphosura', false, 0), $children[2]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2539, 'Pycnogonida', false, 0), $children[3]);

        // Slimoniidae must be descendant of Eurypterida
        $node = $this->service->getNode(8164);
        $this->assertTreeNode(new TreeOfLifeNodeData(8164, 'Slimoniidae', true, 0), $node);

        // Act - delete Eurypterida
        $this->service->deleteSubTree(2537);

        // Assert
        $node = $this->service->getNode(8164);
        $this->assertNull($node);

        $node = $this->service->getNode(2537);
        $this->assertNull($node);

        $children = $this->service->getChildren(2535);
        $this->assertCount(3, $children);
        $this->assertTreeNode(new TreeOfLifeNodeData(2536, 'Arachnida', false, 0), $children[0]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2538, 'Xiphosura', false, 0), $children[1]);
        $this->assertTreeNode(new TreeOfLifeNodeData(2539, 'Pycnogonida', false, 0), $children[2]);
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
