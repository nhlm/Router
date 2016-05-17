<?php
namespace Poirot\Router\Interfaces;

/**
 * Routers Implement This Feature Can Chaining Together
 *
 * x(R-chain1)<->(R-chain2)<-(R-noChain)
 *      v
 * (R-Xrouter)
 */
interface iRouterChain
    extends iRouter
{
    /**
     * Set Nest Link To Next Router
     *
     * - prepend current name to linked router name
     * - linked routes can`t be override
     *
     * @param iRouter $router
     *
     * @return $this
     */
    function link(iRouter $router);

    /**
     * Add Parallel Router
     *
     * @param iRouter $router
     * @param bool    $allowOverride
     *
     * @return $this
     */
    function add(iRouter $router, $allowOverride = true);

    /**
     * Get Parent Chain Leaf
     *
     * @return false|iRouterChain
     */
    function parent();

    /**
     * Explore Router With Name
     *
     * @param string $routeName
     *
     * @return iRouter|false
     */
    function explore($routeName);
}
