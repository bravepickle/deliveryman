<?php
/**
 * Date: 2018-12-22
 * Time: 19:04
 */

namespace Deliveryman\Channel\HttpGraph;


use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Exception\LogicException;

/**
 * Class GraphBuilder builds nodes graph from specified relations
 * @package Deliveryman\Channel\HttpGraph
 */
class GraphTreeBuilder
{
    /**
     * @param array|GraphNode[] $nodes
     * @return array|GraphNode[]
     * @throws LogicException
     */
    public function buildNodes(array $nodes)
    {
        $nodesMap = $this->buildNodesMap($nodes);
        $this->validate($nodes, $nodesMap);
        $this->addRelations($nodesMap);

        return $nodesMap;
    }

    /**
     * @param GraphNode $node
     * @param array $nodesMap
     * @return bool
     * @throws LogicException
     */
    protected function hasCircularReferences(GraphNode $node, array $nodesMap): bool
    {
        $hasCircularRef = false;
        $this->walkRefNodes($nodesMap, $node, function (GraphNode $targetNode) use ($node, &$hasCircularRef) {
            if ($node->getId() === $targetNode->getId()) {
                $hasCircularRef = true;

                return false;
            }

            return true;
        });

        return $hasCircularRef;
    }

    /**
     * @param array|GraphNode[] $nodesMap
     * @param GraphNode $srcNode
     * @param callable $callback
     * @throws LogicException
     */
    protected function walkRefNodes(array $nodesMap, GraphNode $srcNode, callable $callback)
    {
        foreach ($nodesMap as $id => $targetNode) {
            if (in_array($id, $srcNode->getReferenceIds())) {
                if ($callback($targetNode, $srcNode) === false) {
                    return; // stop walking deeper. Terminated by callback
                }

                $this->walkRefNodes($nodesMap, $targetNode, $callback);
            }
        }
    }

    /**
     * @param array|HttpRequest[] $requests
     * @return array|GraphNode[]
     * @throws LogicException
     */
    public function buildNodesFromRequests(array $requests): array
    {
        $nodes = [];
        foreach ($requests as $request) {
            $nodes[] = (new GraphNode())
                ->setId($request->getId())
                ->setReferenceIds($request->getReq())
                ->setData(['request' => $request])
            ;
        }

        return $this->buildNodes($nodes);
    }

    /**
     * Generate assoc map of nodes
     * @param array $nodes
     * @return array|GraphNode[]
     */
    protected function buildNodesMap(array $nodes): array
    {
        $nodesMap = [];
        foreach ($nodes as $node) {
            $nodesMap[$node->getId()] = $node;
        }

        return $nodesMap;
    }

    /**
     * Validate nodes data properly defined
     * @param array|GraphNode[] $nodes
     * @param array|GraphNode[] $nodesMap
     * @throws LogicException
     */
    protected function validate(array $nodes, array $nodesMap): void
    {
        // check for unique ids
        if (count($nodesMap) !== count($nodes)) {
            throw new LogicException('Node IDs must be unique.');
        }

        // check for circular references
        foreach ($nodesMap as $id => $node) {
            if ($this->hasCircularReferences($node, $nodesMap)) {
                throw new LogicException('One or more nodes has circular references: ' . $id . '.');
            }

            foreach ($node->getReferenceIds() as $refId) {
                if (!isset($nodesMap[$refId])) {
                    throw new LogicException("Unknown reference ID found: $refId.");
                }
            }
        }
    }

    /**
     * @param array $nodesMap
     * @throws LogicException
     */
    protected function addRelations(array $nodesMap): void
    {
        foreach ($nodesMap as $node) {
            $this->walkRefNodes($nodesMap, $node, function (GraphNode $targetNode, GraphNode $srcNode) {
                $srcNode->addPredecessor($targetNode);
                $targetNode->addSuccessor($srcNode);
            });
        }
    }
}