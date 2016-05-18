<?php
namespace Poirot\Router\Interfaces;

/**
 * Routers Implement This Feature Can Chaining Together
 *
 * x(R-chain1)<->(R-chain2)<-(R-noChain)
 *      v
 * (R-Xrouter)
 */
interface iRouteChain
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
     * @param bool    $allowOverride
     *
     * @return $this
     */
    function add(iRoute $router, $allowOverride = true);

    /**
     * Get Parent Chain Leaf
     *
     * @return false|iRouteChain
     */
    function parent();

    /**
     * Explore Router With Name
     *
     * @param string $routeName
     *
     * @return iRoute|false
     */
    function explore($routeName);
}
