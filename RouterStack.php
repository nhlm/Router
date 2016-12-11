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
     * @return iRoute|iRouterStack|false usually clone/copy of matched route
     */
    function match(RequestInterface $request)
    {
        ## match against connected routers if exists
        if (empty($this->routesAdd))
            return false;

        # build queue list for routers to match:
        $routes = $this->routesAdd;

        # match routes:
        $routeMatch = false;
        foreach($routes as $r)
            /** @var iRoute $r */
            if ($routeMatch = $r->match($request)) break;

        ## if route match merge stack default params with match route
        /** @var iRoute $routeMatch */
        if ($routeMatch) {
            # $routeMatch = clone $routeMatch; // this can be skipped because use of match on each route
            \Poirot\Router\mergeParamsIntoRouter($routeMatch, $this->params());
        }

        return $routeMatch;
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

        $allowOverride = (bool) $allowOverride;
        if (false === $allowOverride)
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
        # Normalize Route Name
        $routeName = trim((string) $routeName, self::SEPARATOR);


        $selfName = $this->getName();

        if (strpos($routeName, $selfName) !== 0)
            return false;

        # route name exists
        if ($selfName === $routeName)
            ## explore match
            return $this;

        # check on nested routers
        /** @var iRouterStack $nr */
        foreach($this->routesAdd as $nr) {
            if ($routeName === $nr->getName())
                return $nr;

            if ( ($nr instanceof iRouterStack) && ($return = $nr->explore($routeName)) )
                return $return;
        }

        return false;
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
        throw new \Exception('Not Implemented.');
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
        $selfCurrName = $this->getName();

        if ($name == $selfCurrName)
            // Nothing To Do!!!
            return $this;


        # Change Route Name:
        $this->name = (string) $name;


        # Change Name of Nested Routes:
        $nestRoutes = $this->routesAdd;
        foreach($nestRoutes as $nr) {
            $nestedName = $nr->getName();

            $nestedNewName = $name. substr($nestedName, strlen($selfCurrName));
            $nr->setName($nestedNewName);

            unset($this->routesAdd[$nestedName]);
            $this->routesAdd[$nestedNewName] = $nr;
        }

        return $this;
    }


    // ..

    /**
     * !! usually clone/copy of router given to this
     * - assert routes override restriction
     *
     * @param iRoute $router
     *
     * @return iRoute|iRouterStack
     */
    protected function _prepareRouter($router)
    {
        $routeName = $this->getName().self::SEPARATOR.$router->getName();

        $router->setName($routeName);

        ## check if router name exists
        if (array_key_exists($routeName, $this->_routes_strict_override))
            throw new \RuntimeException(sprintf(
                'Router with name (%s) exists and not Allowed Override.'
                , $routeName
            ));

        return $router;
    }
}
