<?php

namespace Canoma;

/**
 * @author Mark van der Velden <mvdvelden@ibuildings.nl>
 */
class Manager
{
    /**
     * @var HashAdapterInterface
     */
    private $adapter;

    /**
     * @var int
     */
    private $replicaCount;

    /**
     * The list with cache nodes
     *
     * @var array
     */
    private $nodes = array();

    /**
     * The ring positions
     *
     * @var array
     */
    private $nodePositions = array();

    /**
     * The positions, per node.
     *
     * @var array
     */
    private $positionsPerNode = array();


    /**
     * Construct the manager, requiring an adapter and a replica count of 0 or more.
     *
     * @param HashAdapterInterface $adapter
     * @param int $replicaCount
     */
    public function __construct(HashAdapterInterface $adapter, $replicaCount)
    {
        $this->adapter = $adapter;
        $this->replicaCount = (int) $replicaCount;
    }


    public function getNodeForString($string)
    {
        $stringPosition = $this->adapter->hash($string);

        // Find the node, that is positioned after the position of the string.
        foreach ($this->nodePositions as $nodePosition => $node) {

            // If the position of the node, is greater than the position of the string, we can return the first hit.
            if ($this->adapter->compare($nodePosition, $stringPosition) > 0) {
                return $node;
            }
        }

        // If we reached the end of our list and still didn't find a suitable node, we pick the first one
        // since that is first one in line in our circle
        return reset($this->nodePositions);
    }


    /**
     * Add a cache-node. The method expects a string argument, representing a node.
     *
     * @param string $node
     * @return Manager
     * @throws \RuntimeException
     */
    public function addNode($node)
    {
        // Sanity check, we only support string types
        if ( ! is_string($node)) {
            throw new \RuntimeException('Expecting a string argument, but $node is not string!');
        }

        if (isset($this->nodes[ $node ])) {
            throw new \RuntimeException('Node already added.');
        }

        // Calculating all positions
        $nodePositions = $this->getNodePositions($node, $this->replicaCount);

        // Storing the positions for this node
        $this->positionsPerNode[ $node ] = $nodePositions;

        // Adding the positions to the 'ring'
        $this->nodePositions = array_merge(
            $this->nodePositions,
            $nodePositions
        );

        // Sort the keys
        ksort($this->nodePositions);

        // Adding the node to the list
        $this->nodes[ $node ] = $node;

        return $this;
    }


    /**
     * Add multiple nodes at once.
     *
     * @param array $nodes
     * @return Manager
     */
    public function addNodes(array $nodes)
    {
        foreach ($nodes as $node) {
            $this->addNode($node);
        }

        return $this;
    }


    /**
     * Return the complete list with nodes
     *
     * @return array
     */
    public function getAllNodes()
    {
        return $this->nodes;
    }


    /**
     * Return a positions of a node
     *
     * @param string $node
     * @return array
     * @throws \RuntimeException
     */
    public function getPositionsOfNode($node)
    {
        if ( ! isset($this->positionsPerNode[$node])) {
            throw new \RuntimeException('Invalid node supplied, no such node has been added.');
        }

        return $this->positionsPerNode[$node];
    }


    /**
     * Return all node positions
     *
     * @return array
     */
    public function getAllPositions()
    {
        return $this->nodePositions;
    }


    /**
     * @return HashAdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }


    /**
     * Return hashes based on the node and the amount of replicas to create.
     *
     * @param string $node
     * @param int $replicaCount
     * @return array
     */
    private function getNodePositions($node, $replicaCount)
    {
        $positions = array();
        for ($i=0; $i < $replicaCount; $i++) {

            // Using a happy separator, since it's unlikely to be used in connection strings
            // It is, however, still possible to have a collision...
            $replicaPosition = $this->adapter->hash("$i^_^$node");
            $positions[$replicaPosition] = $node;
        }

        return $positions;
    }
}
