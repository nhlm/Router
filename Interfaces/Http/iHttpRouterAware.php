<?php
namespace Poirot\Router\Interfaces\Http;

interface iHttpRouterAware 
{
    /**
     * Set Router
     *
     * @param iHRouter $router
     *
     * @return $this
     */
    function setRouter(iHRouter $router);
}
