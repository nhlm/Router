<?php
namespace Poirot\Router\Interfaces;

use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\Router\Interfaces\Http\iHChainingRouter;
use Poirot\Router\Interfaces\Http\iHRouter;

/**
 * Routers Implement This Feature Can Chaining Together
 *
 * x(R-chain1)<->(R-chain2)->(R-noChain)
 *      v
 * (R-Xrouter)
 */
interface iChainingRouterProvider
{
    /**
     * Set Nest Link To Next Router
     *
     * - prepend current name to linked router name
     *
     * @param iHChainingRouter|iHRouter $router
     *
     * @return $this
     */
    function link(/*iHRouter*/ $router);

    /**
     * Add Parallel Router
     *
     * @param iHChainingRouter|iHRouter $router
     *
     * @return $this
     */
    function add(/*iHRouter*/ $router);

    /**
     * Explore Router With Name
     *
     * @param string $routeName
     *
     * @return iHRouter|false
     */
    function explore($routeName);
}
