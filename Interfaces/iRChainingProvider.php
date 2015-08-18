<?php
namespace Poirot\Router\Interfaces;

use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\Router\Interfaces\Http\iHChainRouter;
use Poirot\Router\Interfaces\Http\iHRouter;

/**
 * Routers Implement This Feature Can Chaining Together
 *
 * x(R-chain1)<->(R-chain2)->(R-noChain)
 *
 */
interface iRChainingProvider
{
    /**
     * Set Parent For This Router
     *
     * ! only can have chaining router as parent
     *
     * @param iHChainRouter $router
     *
     * @return $this
     */
    function join(iHChainRouter $router);

    /**
     * Set Nest Link To Next Router
     *
     * ! nest route as both Chaining Router or Simple Router
     *
     * @param iHChainRouter|iHRouter $router
     *
     * @return $this
     */
    function link(/*iHRouter*/ $router);

    /**
     * Add Parallel Router
     *
     * @param iHChainRouter|iHRouter $router
     *
     * @return $this
     */
    function add(/*iHRouter*/ $router);

    /**
     * Explore Router With Name
     *
     * @param $routeName
     *
     * @return iHRouter|false
     */
    function explore($routeName);

    /**
     * Explore Router Match Against Given HttpRequest
     *
     * @param iHttpRequest $request
     *
     * @return iHRouter|false
     */
    function exploreMatch(iHttpRequest $request);
}
