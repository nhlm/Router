<?php
namespace Poirot\Router\Route;

use Psr\Http\Message\RequestInterface;
use Poirot\Router\Interfaces\iRoute;


class RouteMethodSegment
    extends RouteSegment
{
    /** @var string POST|GET|.. */
    protected $method;

    /**
     * Match with Request
     *
     * - merge with current params
     * - manipulate params on match
     *   exp. when match host it contain host param
     *   with matched value
     *
     * @param RequestInterface $request
     *
     * @return RouteSegment|iRoute|false usually clone/copy of matched route
     */
    function match(RequestInterface $request)
    {
        if (strtoupper($request->getMethod()) !== $this->getMethod())
            return false;

        return parent::match($request);
    }

    /**
     * Set Request Method
     *
     * @param string $method Request Method
     *
     * @return $this
     */
    function setMethod($method)
    {
        $this->method = strtoupper( (string) $method );
        return $this;
    }

    /**
     * Criteria Request Method
     *
     * @return string
     */
    function getMethod()
    {
        if (!$this->method)
            $this->setMethod('GET');

        return $this->method;
    }
}
