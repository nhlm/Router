<?php
namespace Poirot\Router\Route;

use Poirot\Router\RouterStack;
use Poirot\Std\Interfaces\Struct\iDataEntity;

use Psr\Http\Message\RequestInterface;

use Poirot\Router\Interfaces\iRoute;
use Poirot\Router\Interfaces\iRouterStack;
use Psr\Http\Message\UriInterface;


class RouteStackChainDecorate
    extends RouterStack
    implements iRouterStack
{
    /** @var iRoute Decorated Route */
    protected $routeInjected;

    /** @var null|RouteStackChainDecorate */
    protected $Parent;

    /**
     * Construct
     * @param iRoute $router Wrap route into stack
     */
    function __construct($router)
    {
        $this->routeInjected = $router;
        parent::__construct($router->getName());
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
        $wrapperMatch = $this->routeInjected->match($request);
        if (!$wrapperMatch)
            return false;

        if (!$this->routeLink && empty($this->routesAdd))
            ## check if has any route added
            return $this; // MUST return self

        ## merge params:
        $routeMatch = clone $this;
        \Poirot\Router\mergeParamsIntoRouter($routeMatch, $wrapperMatch->params());

        ## extract match part from request path uri stack
        #- request:/news/list match:/news follow:/list
        $reqstPath  = $request->getRequestTarget();
        $matchPath  = (string) $wrapperMatch->assemble();
        $followPath = str_replace($matchPath, '', $reqstPath);

        ## then match against connected routers if exists
        $request    = $request->withRequestTarget($followPath);
        $routeMatch = $routeMatch->matchParent($request);
        return $routeMatch;
    }
    
    protected function matchParent($request)
    {
        return parent::match($request);
    }

    /**
     * Assemble the route to string with params
     *
     * - use default parameters self::params
     * - given parameters merged into defaults
     *
     * @param array|\Traversable $params    Override defaults by merge
     * @param string|null        $routename Route name to explore
     *
     * @return UriInterface
     * @throws \RuntimeException route not found
     */
    function assemble($params = null, $routename = null)
    {
        if ($routename !== null) {
            if (false !== $route = $this->explore($routename))
                throw new \RuntimeException(sprintf('Route (%s) not found.', $routename));

            return $route->assemble($params);
        }

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


    // ..

    /**
     * - make copy of original route
     *
     * @param iRoute $router
     * @return RouteStackChainDecorate
     */
    protected function _prepareRouter($router)
    {
        $router = new self($router);
        $router->Parent = $this;
        $router = parent::_prepareRouter($router);
        return $router;
    }

    function __clone()
    {
        $this->routeInjected = clone $this->routeInjected;
    }
}
