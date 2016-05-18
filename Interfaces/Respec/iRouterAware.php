<?php
namespace Poirot\Router\Interfaces\Respec;

use Poirot\Router\Interfaces\iRoute;

interface iRouterAware 
{
    /**
     * Set Router
     *
     * @param iRoute $router
     *
     * @return $this
     */
    function setRouter(iRoute $router);
}
