<?php
namespace Poirot\Router\Route;

use Poirot\Router\RouterStack;
use Poirot\Std\Interfaces\Struct\iDataEntity;

use Psr\Http\Message\RequestInterface;

use Poirot\Router\Interfaces\iRoute;
use Poirot\Router\Interfaces\iRouterStack;
use Psr\Http\Message\UriInterface;


/*
$rStack = new P\Router\Route\RouteStackChainDecorate(
    new P\Router\Route\RouteSegment('news', ['criteria' => '/news', 'match_whole' => false])
);
$rStack->add(
    new P\Router\Route\RouteSegment('about', ['criteria' => '/list'])
);



$r = $rStack->match($request);
echo ($r->assemble());
*/

class RouteStackChainWrapper
    extends RouterStack
    implements iRouterStack
{
    /** @var iRoute Decorated Route */
    protected $routeInjected;

    /**
     * Construct
     * @param iRoute $router Wrap route into stack
     */
    function __construct(iRoute $router)
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
        if (! $wrapperMatch = $this->routeInjected->match($request) )
            // The chain has broken; request does not match with injected route criteria
            return false;


        // Because each RouteStack can explore through name, each add route must responsible to assemble route url
        $routeMatch = ($wrapperMatch instanceof iRouterStack) ? $wrapperMatch : $this;

        # Routes also can match with nested

        if (empty($this->routesAdd))
            ## check if has any route added
            return $routeMatch; // MUST return matched route

        ## merge params:

        // Data Already present by calling match on wrapperMatch
        // \Poirot\Router\mergeParamsIntoRouter($routeMatch, $wrapperMatch->params());

        ## extract match part from request path uri stack
        #- request:/news/list match:/news follow:/list
        $reqstPath  = $request->getRequestTarget();
        $matchPath  = (string) $wrapperMatch->assemble();
        // TODO better replacement method
        $followPath = substr($reqstPath, strlen($matchPath));

        ## then match against connected routers if exists
        $request   = $request->withRequestTarget($followPath);
        if ($match = $routeMatch->matchParent($request)) {
            // cant match nested but still match with injected route that seem is OK!
            // TODO Set Option To Define This Behaviour
            // Sometimes Chaining Routes Defined as Only namespace or group of other routes
            // and main route is not responsible for any actions
            // currently in exp. with /members /members/signin /members/recovery
            // /members must not rendered from request; for now it's does
            $routeMatch = $match;
        }

        return $routeMatch;
    }
    
    protected function matchParent($request)
    {
        return parent::match($request);
    }

    /**
     * Explore Router With Name
     *
     * - route name must start with self router name
     * !! the names separated by "/"
     *
     * @param string $routeName
     *
     * @return iRoute|false
     */
    function explore($routeName)
    {
        if (method_exists($this->routeInjected, 'explore')) {
            if ($route = $this->routeInjected->explore($routeName))
                // We can also have wrapper of RouterStack
                return $route;
        }

        return parent::explore($routeName);
    }

    /**
     * Assemble the route to string with params
     *
     * - use default parameters self::params
     * - given parameters merged into defaults
     *
     * @param array|\Traversable $params Override defaults by merge
     *
     * @return UriInterface
     * @throws \Exception
     */
    function assemble($params = null)
    {
        $route = $this;
        $uri   = $route->routeInjected->assemble($params);
        if ($route = $route->hasParent()) {
            $rUri = $route->assemble($params);
            $uri  = \Poirot\Psr7\modifyUri($uri, \Poirot\Psr7\parseUriPsr($rUri), \Poirot\Psr7\URI_PREPEND);
        }

        return $uri;
    }

    /**
     * Set Route Name
     *
     * @param string $name
     *
     * @return $this
     */
    function setName($name)
    {
        // notify injected route that name changed!!
        $this->routeInjected->setName($name);

        // also change name of wrapper
        parent::setName($name);
        return $this;
    }

    /**
     * Route Default Params
     *
     * @return iDataEntity
     */
    function params()
    {
        return $this->routeInjected->params();
    }


    // ...

    function __clone()
    {
        if (null !== $this->routeInjected)
            $this->routeInjected = clone $this->routeInjected;
    }
}
