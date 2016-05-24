<?php
namespace Poirot\Router\Interfaces;

use Psr\Http\Message\UriInterface;

/**
 * Routers Implement This Feature Can Chaining Together
 *
 * x(R-chain1)<->(R-chain2)<-(R-noChain)
 *      v
 * (R-Xrouter)
 */
interface iRouterStack
    extends iRoute
{
    /**
     * Set Nest Link To Next Router
     *
     * - prepend current name to linked router name
     * - linked routes can`t be override
     *
     * @param iRoute $router
     *
     * @return $this
     */
    function link(iRoute $router);

    /**
     * Add Parallel Router
     *
     * @param iRoute $router
     * @param bool   $allowOverride
     *
     * @return $this
     */
    function add(iRoute $router, $allowOverride = true);

    /**
     * Assemble the route to string with params
     *
     * - use default parameters self::params
     * - given parameters merged into defaults
     *
     * @param array|\Traversable $params    Override defaults by merge
     * @param string|null        $routename Route name to explore
     * 
     * @return UriInterface
     * @throws \RuntimeException route not found
     */
    function assemble($params = null, $routename = null);

    /**
     * Explore Router With Name
     *
     * - names are always in form of append list
     *   route_main\other_route\route
     *
     * @param string $routeName
     *
     * @return iRoute|false
     */
    function explore($routeName);
}
