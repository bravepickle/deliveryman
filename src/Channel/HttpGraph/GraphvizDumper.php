<?php
/**
 * Date: 2018-12-23
 * Time: 22:24
 */

namespace Deliveryman\Channel\HttpGraph;

/**
 * Class GraphvizDumper
 * Dump graph nodes as graphviz dot format
 * @package Deliveryman\Channel\HttpGraph
 */
class GraphvizDumper
{
    const OPT_DEFAULTS = [
        'graph' => ['ratio' => 'compress'],
        'node' => ['fontsize' => 11, 'fontname' => 'Arial', 'shape' => 'record'],
        'edge' => ['fontsize' => 9, 'fontname' => 'Arial', 'color' => 'grey', 'arrowhead' => 'open', 'arrowsize' => 0.5],
    ];

    /**
     * @var GraphNodeCollection
     */
    protected $collection;

    /**
     * @var array Graph options
     */
    protected $options = self::OPT_DEFAULTS;

    /**
     * GraphvizDumper constructor.
     * @param array $options
     */
    public function __construct(?array $options = null)
    {
        if ($options !== null) {
            $this->options = array_merge_recursive($this->options, $options);
        }
    }

    /**
     * Dump nodes to generated string
     * @param GraphNodeCollection $collection
     * @return string
     */
    public function dump(GraphNodeCollection $collection): string
    {
        $this->reset();
        $this->collection = $collection;

        $rows = [];
        $this->addGraphDefinition($rows);
        $this->addNodeDefinition($rows);
        $this->addNodesAndEdges($rows);

        return $this->buildGraph($rows);
        

        //        $arrows = $this->collection->flattenedArrows();
    }

    /**
     * @param array $rows
     * @return string
     */
    protected function buildGraph(array $rows): string
    {
        $lines = [];
        $lines[] = 'digraph G {';

        foreach ($rows as $row) {
            $lines[] = implode(' ', $row) . ';';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @param GraphNode $node
     * @return bool|string
     */
    protected function genLabel(GraphNode $node)
    {
        return substr(spl_object_hash($node), 0, 5);
    }

    protected function reset()
    {
        $this->collection = null;
    }

    /**
     * @param array $rows
     */
    protected function addNodeDefinition(array &$rows): void
    {
        $nodeOpts = [];
        foreach ($this->options['node'] as $name => $value) {
            $nodeOpts[] = $name . '="' . $value . '"';
        }

        if ($nodeOpts) {
            $row = ["node"];
            $row[] = '[' . implode(' ', $nodeOpts) . ']';
            $rows[] = $row;
        }
    }

    /**
     * @return string
     */
    protected function genEdgeDefinition(): string
    {
        $opts = [];
        foreach ($this->options['edge'] as $name => $value) {
            $opts[] = $name . '="' . $value . '"';
        }

        return '[' . implode(' ', $opts) . ']';
    }

    /**
     * @param GraphNode $node
     * @return string
     */
    protected function genNodeInstanceDefinition(GraphNode $node): string
    {
        $opts = ['label="' . $this->genLabel($node) . '"'];

        return '[' . implode(' ', $opts) . ']';
    }

    /**
     * @param array $rows
     */
    protected function addGraphDefinition(array &$rows): void
    {
        foreach ($this->options['graph'] as $name => $value) {
            $rows[] = $name . '="' . $value . '"';
        }
    }

    /**
     * @param array $rows
     */
    protected function addNodesAndEdges(array &$rows): void
    {
        /** @var GraphNode $node */
        foreach ($this->collection->arrowTailsIterator() as $node) {
            $rows[] = [$node->getId(), $this->genNodeInstanceDefinition($node)];
            foreach ($node->getSuccessors() as $successor) {
                $rows[] = [$this->genLabel($node), $this->genLabel($successor), $this->genEdgeDefinition()];
            }
        }
    }
}