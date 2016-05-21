<?php
namespace Poirot\Router\Route;

use Poirot\Psr7\Uri;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

use Poirot\Router\aRoute;
use Poirot\Router\Interfaces\iRoute;

class RouteMethod 
    extends aRoute
{
    /** @var string */
    protected $method;

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
    function match(RequestInterface $request)
    {
        $method = $request->getMethod();

        if (strtoupper($method) !== strtoupper($this->getMethod()))
            return false;

        $routeMatch = clone $this;
        return $routeMatch;
    }

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return UriInterface
     */
    function assemble($params = array())
    {
        return new Uri;
    }
    
    
    // ..

    /**
     * Set Method
     *
     * @param string $method
     *
     * @return $this
     */
    function setMethod($method)
    {
        $this->method = (string) $method;
        return $this;
    }

    /**
     * Get Method
     *
     * @return string
     */
    function getMethod()
    {
        return $this->method;
    }
}
