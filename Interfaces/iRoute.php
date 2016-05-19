<?php
namespace Poirot\Router\Interfaces;

use Poirot\Std\Interfaces\Struct\iDataEntity;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

interface iRoute
{
    /**
     * Set Route Name
     * 
     * @param string $name
     * 
     * @return $this
     */
    function setName($name);
    
    /**
     * Get Router Name
     *
     * @return string
     */
    function getName();

    /**
     * Match with Request
     * 
     * - on match extract request params and merge
     *   into default params
     * 
     * !! don`t change request object attributes
     *
     * @param RequestInterface $request
     *
     * @return iRoute|false
     */
    function match(RequestInterface $request);

    /**
     * Assemble the route to string with params
     *
     * @param array|\Traversable $params Params to merge
     *
     * @return UriInterface
     */
    function assemble($params = null);

    /**
     * Route Default Params
     *
     * @return iDataEntity
     */
    function params();
}
