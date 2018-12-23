<?php
/**
 * Date: 2018-12-22
 * Time: 19:06
 */

namespace Deliveryman\Channel\HttpGraph;


use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Entity\IdentifiableInterface;
use Deliveryman\Exception\LogicException;

/**
 * Class GraphNode
 * @package Deliveryman\Channel\HttpGraph
 */
class GraphNode implements IdentifiableInterface
{
    /**
     * @var mixed
     */
    protected $id;

    /**
     * @var array
     */
    protected $referenceIds = [];

    /**
     * Payload data
     * @var mixed
     */
    protected $data;

    /**
     * Nodes that reference to this one
     * @var array
     */
    protected $predecessors = [];

    /**
     * Nodes this node references to
     * @var array
     */
    protected $successors = [];

    /**
     * GraphNode constructor.
     * @param null $id
     * @param array $referenceIds
     * @param HttpRequest $data
     */
    public function __construct($id = null, array $referenceIds = [], $data = null)
    {
        $this->id = $id;
        $this->referenceIds = $referenceIds;
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return GraphNode
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return array
     */
    public function getReferenceIds(): array
    {
        return $this->referenceIds;
    }

    /**
     * @param array $referenceIds
     * @return GraphNode
     */
    public function setReferenceIds(array $referenceIds): GraphNode
    {
        $this->referenceIds = $referenceIds;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return GraphNode
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param GraphNode $node
     * @return $this
     * @throws LogicException
     */
    public function addSuccessor(GraphNode $node)
    {
        $this->successors[$node->getId()] = $node;
        $node->addPredecessor($this);

        return $this;
    }

    /**
     * @return array|GraphNode[]
     */
    public function getPredecessors(): array
    {
        return $this->predecessors;
    }

    /**
     * @return array|GraphNode[]
     */
    public function getSuccessors(): array
    {
        return $this->successors;
    }

    /**
     * @param GraphNode $node
     * @return $this
     */
    public function addPredecessor(GraphNode $node)
    {
        $this->predecessors[$node->getId()] = $node;

        return $this;
    }

}