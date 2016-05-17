<?php
namespace Poirot\Router\Interfaces\Respec;

use Poirot\Router\Interfaces\iRouter;

interface iRouterAware 
{
    /**
     * Set Router
     *
     * @param iRouter $router
     *
     * @return $this
     */
    function setRouter(iRouter $router);
}
