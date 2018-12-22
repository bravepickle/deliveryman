<?php
/**
 * Date: 2018-12-22
 * Time: 19:06
 */

namespace Deliveryman\Channel\HttpGraph;


use Deliveryman\Entity\HttpGraph\HttpRequest;
use Deliveryman\Entity\IdentifiableInterface;
use Deliveryman\Exception\LogicException;

class GraphNode implements IdentifiableInterface
{
    /**
     * @var HttpRequest
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
     * @param HttpRequest $data
     */
    public function __construct(HttpRequest $data = null)
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     * @throws LogicException
     */
    public function getId()
    {
        if (!$this->getData()) {
            throw new LogicException('Graph node data is not set');
        }

        return $this->getData()->getId();
    }

    public function getReferenceIds(): array
    {
        return (array)$this->getData()->getReq();
    }

    /**
     * @return HttpRequest
     */
    public function getData(): HttpRequest
    {
        return $this->data;
    }

    /**
     * @param HttpRequest $data
     * @return GraphNode
     */
    public function setData(HttpRequest $data): GraphNode
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
     * @throws LogicException
     */
    public function addPredecessor(GraphNode $node)
    {
        $this->predecessors[$node->getId()] = $node;

        return $this;
    }

}