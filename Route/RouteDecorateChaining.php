<?php
namespace Poirot\Router\Route;

use Poirot\Router\RouterChain;
use Poirot\Std\Interfaces\Struct\iDataEntity;

use Poirot\Psr7\Uri;

use Psr\Http\Message\RequestInterface;

use Poirot\Router\Interfaces\iRoute;
use Poirot\Router\Interfaces\iRouterChain;


class RouteDecorateChaining 
    extends RouterChain
    implements iRouterChain
{
    /** @var iRoute Decorated Route */
    protected $routeInjected;

    /**
     * Construct
     *
     * @param iRoute $router Wrapper around router
     */
    function __construct($router)
    {
        $this->routeInjected = $router;
        $this->name = $router->getName();
    }

    /**
     * Match with Request
     *
     * - on match extract request params and merge
     *   into default params
     *
     * !! don`t change request object attributes
     *
     * @param RequestInterface $request
     *
     * @return iRoute|false
     */
    function match(RequestInterface $request)
    {
        # first must match with wrapped router
        /** @var iRoute $wrapperMatch */
        $wrapperMatch = call_user_func_array(
            array($this->routeInjected, 'match'), func_get_args()
        ); ## ->match($request);

        // TODO what??
        if (!$wrapperMatch)
            return false;

        $routerMatch = clone $this;
        $routerMatch->params()->import($wrapperMatch->params());

        ## then match against connected routers if exists
        if ($this->routeLinked || !empty($this->routesAdded))
            $routerMatch = parent::match($request);
        
        return $routerMatch;
    }

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return Uri
     */
    function assemble($params = array())
    {
        # first assemble from wrapped resource router
        $httpUri = $this->routeInjected->assemble($params);

        if ($this->parent()) {
            ## merge with parent leaf assembled properties
            $parentUri = $this->parent()->assemble($params);
            $parentUri = $parentUri->toArray();

            if (isset($parentUri['path'])) {
                ### paths must prepend to uri
                $httpUri->getPath()->prepend($parentUri['path']);
                unset($parentUri['path']);
            }
            if (isset($parentUri['query'])) {
                ### query strings must merged
                $httpUri->getQuery()->from($parentUri['query']);
                unset($parentUri['query']);
            }

            ### all going replaced
            if(!empty($parentUri))
                $httpUri->from($parentUri);
        }

        return $httpUri;
    }

    /**
     * Route Default Params
     *
     * @return iDataEntity
     */
    function params()
    {
        if (!$this->params)
            $this->params = clone $this->routeInjected->params();

        return $this->params;
    }
}
