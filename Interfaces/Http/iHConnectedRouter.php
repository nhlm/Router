<?php
namespace Poirot\Router\Interfaces\Http;

use Poirot\Router\Interfaces\iConnectedRouterProvider;

/**
 * Chaining Http Router
 *
 */
interface iHConnectedRouter
    extends iHRouter
    , iConnectedRouterProvider
{

}
