<?php
namespace Poirot\Router\Interfaces;

use Poirot\Std\Interfaces\Struct\iData;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

interface iRoute
{
    /**
     * Construct
     *
     * @param string $name Router Name
     */
    function __construct($name);

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
     * @param array $params
     *
     * @return UriInterface
     */
    function assemble(array $params = array());

    /**
     * Route Default Params
     *
     * @return iData
     */
    function params();
}
