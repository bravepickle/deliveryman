<?php
/**
 * Date: 2018-12-23
 * Time: 15:52
 */

namespace Deliveryman\Channel\HttpGraph;

/**
 * Class GraphNodeCollection
 * @package Deliveryman\Channel\HttpGraph
 */
class GraphNodeCollection
{
    /**
     * @var array|GraphNode[]
     */
    protected $nodes = [];

    /**
     * GraphNodeCollection constructor.
     * @param array|GraphNode[] $items
     */
    public function __construct(array $items = [])
    {
        $this->setNodes($items);
    }

    /**
     * @return array|GraphNode[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    /**
     * @param array|GraphNode[] $nodes
     * @return GraphNodeCollection
     */
    public function setNodes(array $nodes): GraphNodeCollection
    {
        $this->nodes = $nodes;

        return $this;
    }

    /**
     * Return iterator walk through all nodes that have no predecessors
     * @return \Generator
     */
    public function arrowTailsIterator()
    {
        foreach ($this->nodes as $item) {
            if (!$item->getPredecessors()) {
                yield $item;
            }
        }
    }

    /**
     * Return list of flattened arrows made of nodes and their dependencies
     * Some nodes may appear multiple times
     * Example: [A -> B -> C, D -> B -> E]
     * TODO: remove me if not used
     */
    public function flattenedArrows()
    {
        $arrows = [];
        /** @var GraphNode $node */
        foreach ($this->arrowTailsIterator() as $node) {
            $this->walkArrow($node, [], $arrows);
        }

        return $arrows;
    }

    protected function walkArrow(GraphNode $node, array $arrow, array &$arrows)
    {
        $arrow[$node->getId()] = $node;
        if ($node->getSuccessors()) {
            foreach ($node->getSuccessors() as $successor) {
                $this->walkArrow($successor, $arrow, $arrows);
            }
        } else {
            $arrows[] = $arrow; // head of arrow reached
        }
    }

    /**
     * @param GraphNode $srcNode
     * @param callable $callback
     */
    protected function walkRefNodes(GraphNode $srcNode, callable $callback)
    {
        foreach ($this->nodes as $targetNode) {
            if (in_array($targetNode->getId(), $srcNode->getReferenceIds())) {
                if ($callback($targetNode, $srcNode) === false) {
                    return; // stop walking deeper. Terminated by callback
                }

                $this->walkRefNodes($targetNode, $callback);
            }
        }
    }

}