<?php
namespace Poirot\Router\Route;

use Poirot\Psr7\Uri;
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

    /** @var null|RouteStackChainWrapper */
    protected $Parent;

    /**
     * Construct
     * @param iRoute $router Wrap route into stack
     */
    function __construct(iRoute $router)
    {
        $this->routeInjected = clone $router;
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
            return $wrapperMatch; // MUST return matched route

        ## merge params:
        $routeMatch = clone $this;
        // Data Already present by calling match on wrapperMatch
        // \Poirot\Router\mergeParamsIntoRouter($routeMatch, $wrapperMatch->params());

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

        $route = $this; $rUri = new Uri();
        while($route) {
            $uri   = $route->routeInjected->assemble();
            $rUri  = \Poirot\Psr7\modifyUri($rUri, \Poirot\Psr7\parseUriPsr($uri), \Poirot\Psr7\URI_PREPEND);
            
            $route = $route->Parent;
        }

        return $rUri;
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
        parent::setName($name);
        $this->routeInjected->setName($name); // also change injected route name as same
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


    // ..

    /**
     * - make copy of original route
     *
     * @param iRoute $router
     * @return RouteStackChainWrapper
     */
    protected function _prepareRouter($router)
    {
        $router = new self($router);
        $router->Parent = $this;
        $router = parent::_prepareRouter($router);
        return $router;
    }
}
