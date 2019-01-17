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
        'dumper' => ['alias_size' => 3],
        'graph' => ['ratio' => 'compress', 'rankdir' => 'LR',],
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
     * @var array
     */
    protected $labels = [];

    /**
     * @var int
     */
    protected $indexLabel = 0;

    /**
     * GraphvizDumper constructor.
     * @param array $options
     */
    public function __construct(?array $options = null)
    {
        if ($options !== null) {
            foreach ($options as $groupName => $group) { // merge
                $this->options[$groupName] = array_merge($this->options[$groupName], $group);
            }
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
            $lines[] = '  ' . implode(' ', $row) . ';';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * @param GraphNode $node
     * @return string
     */
    protected function genLabel(GraphNode $node)
    {
        if (!preg_match('/[^[:ascii:]]/', $node->getId())) { // do we have forbidden symbols?
            return $node->getId();
        }

        // generate new alias
        return 'node_' . substr(md5(spl_object_hash($node)), 0, $this->options['dumper']['alias_size']);
    }

    /**
     * @param GraphNode $node
     * @return bool|string
     */
    protected function genDotId(GraphNode $node)
    {
        $key = md5(spl_object_hash($node));
        if (!isset($this->labels[$key])) {
            $this->labels[$key] = 'l' . ++$this->indexLabel;
        }

        return $this->labels[$key];
    }

    protected function reset()
    {
        $this->labels = [];
        $this->indexLabel = 0;
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
            $rows[] = [$name . '="' . $value . '"'];
        }
    }

    /**
     * @param array $rows
     */
    protected function addNodesAndEdges(array &$rows): void
    {
        /** @var GraphNode $node */
        foreach ($this->collection->getNodes() as $node) {
            $rows[] = [$this->genDotId($node), $this->genNodeInstanceDefinition($node)];
        }

        foreach ($this->collection->getNodes() as $node) {
            foreach ($node->getSuccessors() as $successor) {
                $rows[] = [$this->genDotId($node), '->', $this->genDotId($successor), $this->genEdgeDefinition()];
            }
        }
    }
}