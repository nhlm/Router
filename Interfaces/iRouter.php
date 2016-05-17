<?php
namespace Poirot\Router\Interfaces;

use Poirot\Std\Interfaces\Pact\ipFactory;
use Poirot\Std\Interfaces\Struct\iData;
use Psr\Http\Message\RequestInterface;

interface iRouter
    extends ipFactory
{
    // Implement Factory:
    
    /**
     * Create a new route with given options
     *
     * @param array $valuable Builder Factory Config
     *
     * @throws \InvalidArgumentException
     * @return iRouter
     */
    static function of($valuable);

    
    // Implement Features:
    
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
     * @return iRouter|false
     */
    function match(RequestInterface $request);

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return string
     */
    function assemble(array $params = array());

    /**
     * Route Default Params
     *
     * @return iData
     */
    function params();
}
