<?php
namespace Poirot\Router;

use Poirot\Router\Route\RouteStackChainWrapper;
use Psr\Http\Message\RequestInterface;

use Poirot\Router\Interfaces\iRoute;
use Poirot\Router\Interfaces\iRouterStack;
use Psr\Http\Message\UriInterface;

/*
$request = new P\Http\HttpRequest(new P\Http\HttpMessage\Request\DataParseRequestPhp());

$builder = new P\Router\BuildRouterStack(array(
    'routes' => array(
        'oauth'  => array(
            'route' => 'RouteSegment',
            'options' => array(
                'criteria'    => '/oauth',
                'match_whole' => false,
            ),
            'params'  => array(
                ListenerDispatch::CONF_KEY => function($services)
                {
                    echo 'I`m running on each route match on children or itself.';
                },
            ),
            'routes' => array(
                'authorize' => array(
                    'route' => 'RouteSegment',
                    'options' => array(
                        'criteria'    => '/authorize',
                    ),
                    'params'  => array(
                        ListenerDispatch::CONF_KEY => function($services)
                        {
                            kd('Authorize');
                        },
                    ),
                ),
                'token' => array(
                    'route' => 'RouteSegment',
                    'options' => array(
                        'criteria'    => '/token',
                    ),
                    'params'  => array(
                        ListenerDispatch::CONF_KEY => function($services)
                        {
                            kd('Token');
                        },
                    ),
                ),
            ),
        ),
    ),
));

$router  = new P\Router\RouterStack('main');
$builder->build($router);

$match = $router->match(new P\Http\Psr\RequestBridgeInPsr($request));
if ($match)
    kd('Matched:', P\Std\cast($match->params())->toArray());
*/

class RouterStack
    extends aRoute
    implements iRouterStack
{
    /** Separate route chain names */
    const SEPARATOR = '/';

    /** @var iRoute Nest Right Link */
    protected $routeLink;
    
    /** @var iRoute[] Parallel Routers */
    public $routesAdd = array();

    protected $_routes_strict_override = array(
        # just having route name here mean strict from override
        ## 'route_name' => true
    );


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
     * @return iRoute|false usually clone/copy of matched route
     */
    function match(RequestInterface $request)
    {
        ## match against connected routers if exists
        if (!$this->routeLink && empty($this->routesAdd))
            return false;

        # build queue list for routers to match:
        $routers = $this->routesAdd;
        ## prepend link route at match stack 
        (empty($this->routeLink)) ?: array_unshift($routers, $this->routeLink);

        # match routes:
        $routeMatch = false;
        foreach($routers as $r) {
            /** @var iRoute $r */
            if ($routeMatch = $r->match($request)) break;
        }

        ## if route match merge stack default params with match route
        /** @var iRoute $routeMatch */
        if ($routeMatch)
            \Poirot\Router\mergeParamsIntoRouter($routeMatch, $this->params());

        return $routeMatch;
    }

    /**
     * Set Nest Link To Next Router
     *
     * - set self as parent of linked router
     * - prepend current name to linked router name
     *
     * @param iRoute $router
     *
     * @return $this
     */
    function link(iRoute $router)
    {
        if ($this->routeLink)
            throw new \RuntimeException('Linked router found and can`t be override.');

        $router = $this->_prepareRouter($router);
        $this->routeLink  = $router;
        return $this;
    }

    /**
     * Add Parallel Router
     *
     * - set self as parent of linked router
     * - prepend current name to linked router name
     *
     * @param iRoute $router
     * @param bool   $allowOverride
     *
     * @return $this
     */
    function add(iRoute $router, $allowOverride = true)
    {
        $router = $this->_prepareRouter($router);
        $this->routesAdd[$router->getName()] = $router;

        if (!$allowOverride)
            $this->_routes_strict_override[$router->getName()] = true;

        return $this;
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
        $selfName = $this->getName();

        if (strpos($routeName, $selfName) !== 0)
            return false;

        # route name exists
        if ($selfName === $routeName)
            ## explore match
            return $this;

        # check on nested routers
        $nestRoutes = $this->routesAdd;
        if ($this->routeLink)
            ## prepend linked router for first check
            array_unshift($nestRoutes, $this->routeLink);

        /** @var iRouterStack $nr */
        foreach($nestRoutes as $nr) {
            if ($nr instanceof  iRouterStack && $return = $nr->explore($routeName))
                return $return;
            elseif ($routeName === $nr->getName())
                return $nr;
        }

        return false;
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
        if (false === $route = $this->explore($routename))
            throw new \RuntimeException(sprintf('Route (%s) not found.', $routename));

        return $route->assemble($params);
    }

    /**
     * // TODO Improve logic; when parent root name change all nested root must change prefix parent name
     * 
     * Set Route Name
     *
     * @param string $name
     *
     * @return $this
     */
    function setName($name)
    {
        $selfCurrName = $this->getName();

        foreach($this->routesAdd as $nr) {
            // Change the name of all nested route
            $nestedName = $nr->getName();
            $nestedNewName = str_replace($selfCurrName, $name, $nestedName);
            $nr->setName($nestedNewName);
            unset($this->routesAdd[$nestedName]);
            $this->routesAdd[$nestedNewName] = $nr;
        }

        $this->name = (string) $name;
        return $this;
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
        ## check if router name exists
        $routeName = $router->getName();
        if (array_key_exists($routeName, $this->_routes_strict_override))
            throw new \RuntimeException(sprintf(
                'Router with name (%s) exists.'
                , $router->getName()
            ));


        // $router = clone $router;
        $router->setName($this->getName().self::SEPARATOR.$router->getName());
        return $router;
    }
}
