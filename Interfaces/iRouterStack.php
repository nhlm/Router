<?php
namespace Poirot\Router\Interfaces;


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
     * Add Parallel Router
     *
     * @param iRoute $router
     * @param bool   $allowOverride
     *
     * @return $this
     */
    function add(iRoute $router, $allowOverride = true);

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
