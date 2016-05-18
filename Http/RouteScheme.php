<?php
namespace Poirot\Router\Http;

use GuzzleHttp\Psr7\Uri;
use Poirot\Router\Interfaces\iRoute;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class RouteScheme 
    extends aRoute
{
    /** @var string */
    protected $scheme = 'http';

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
        $uri    = $request->getUri();
        $scheme = $uri->getScheme();

        if ($scheme !== $this->getScheme())
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
    function assemble(array $params = array())
    {
        $uri = new Uri();
        $uri->withScheme($this->getScheme());
        return $uri;
    }
    
    
    // ..

    /**
     * Set Scheme
     *
     * @param string $scheme
     *
     * @return $this
     */
    function setScheme($scheme)
    {
        $this->scheme = $scheme;
        return $this;
    }

    /**
     * Get Scheme
     *
     * @return string
     */
    function getScheme()
    {
        return $this->scheme;
    }
}
