<?php
namespace Poirot\Router;

use Poirot\Psr7\Uri;
use Poirot\Router\Route\RouteDecorateChaining;
use Psr\Http\Message\RequestInterface;

use Poirot\Std\Type\StdArray;

use Poirot\Router\Interfaces\iRoute;
use Poirot\Router\Interfaces\iRouterChain;


/**
 * [code]
 *   $Router = new RChainStack('main');
 *   $Router
 *       ->add(new RSegment('pages',
 *               [
 *                   'criteria'    => '/pages',
 *                   'exact_match' => false,
 *               ]
 *               , ['__action__' => 'check_user']
 *           )
 *       )->recent() ## recently added segment route "pages" as chain router
 *       ->link(new RSegment('static',
 *           [
 *               'criteria'    => 'static/mypage',
 *           ]
 *           , ['__action__' => 'display_static_mypage']
 *       ))
 *       ->add(new RSegment('page', ## add to "pages" recently router
 *           [
 *               'criteria'    => [':request_page' => ['request_page'=>'\w+'], ],
 *           ]
 *           , ['__action__' => 'display_page']
 *       ))
 *       ->parent() ## get back to parent "main" chain router stack
 *       ->add(new RSegment('auth',
 *           [
 *               'criteria'    => '/register',
 *           ]
 *       ));
 *   ;
 * [/code]
 */
class RouterChain
    extends aRoute
    implements iRouterChain
{
    /** Separate route chain names */
    const SEPARATOR = '/';

    /** @var iRoute Nest Right Link */
    protected $routeLinked;
    
    /** @var iRoute[] Parallel Routers */
    protected $routesAdded = array();
    
    /**
     * Parent Leaf for linked routers
     * @var iRouterChain
     */
    protected $parent = null;

    /**
     * Recent added router
     * @var iRouterChain
     */
    protected $_c__recent;
    
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
     * @return iRoute|false
     */
    function match(RequestInterface $request)
    {
        ## then match against connected routers if exists
        if (!$this->routeLinked && empty($this->routesAdded))
            return false;

        # build queue list for routers to match:
        $routers     = $this->routesAdded;
        ## right leaf link check for match first
        (empty($this->routeLinked)) ?: array_unshift($routers, $this->routeLinked);

        # match routes:
        $routerMatch = false;
        /** @var iRouterChain $r */
        foreach($routers as $r)
            ## $r->match($request)
            if ($routerMatch = call_user_func_array(array($r, 'match'), func_get_args()))
                break;

        ## if route match merge matched params into default
        /** @var iRoute $routerMatch */
        if ($routerMatch) {
            $mergeParams = new StdArray(\Poirot\Std\cast($this->params())->toArray());
            $mergeParams->mergeRecursive(\Poirot\Std\cast($routerMatch->params())->toArray());
            $routerMatch->params()->import($mergeParams);
        }

        return $routerMatch;
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
        if (strlen($selfName) == strlen($routeName))
            ## explore match
            return $this;

        # check on nested routers
        $nestRoutes = $this->routesAdded;
        if ($this->routeLinked)
            ## prepend linked router for first check
            array_unshift($nestRoutes, $this->routeLinked);

        /** @var iRouterChain $nr */
        foreach($nestRoutes as $nr) {
            if ($nr instanceof  iRouterChain && $return = $nr->explore($routeName))
                return $return;
            elseif ($routeName === $nr->getName())
                return $nr;
        }

        return false;
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
        $router = $this->_prepareRouter($router);

        $this->routeLinked = $router;
        $this->_routes_strict_override[$router->getName()] = true;

        $this->_c__recent = $router;
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
        $this->routesAdded[$router->getName()] = $router;
        
        if (!$allowOverride)
            $this->_routes_strict_override[$router->getName()] = true;

        $this->_c__recent = $router;
        return $this;
    }

    /**
     * Get Parent Chain Leaf
     *
     * @return false|iRouterChain
     */
    function parent()
    {
        return $this->parent;
    }
    
    /**
     * Helper To Get Recent Chained Route
     * @return iRouterChain|iRoute
     */
    function recent()
    {
        if (!$this->_c__recent)
            $this->_c__recent = $this;

        return $this->_c__recent;
    }
    
    /**
     * Assemble the route to string with params
     * @param array $params
     * @return Uri
     * @throws \Exception
     */
    function assemble($params = array())
    {
        throw new \Exception('Chain Router Assemble Not Implemented.');
    }
    
    
    // ..

    /**
     * @param iRoute $router
     * @return RouteDecorateChaining
     */
    protected function _prepareRouter($router)
    {
        $router = clone $router;
        $router->setName($this->getName().self::SEPARATOR.$router->getName());

        ## check if router name exists
        $routeName = $router->getName();

        if (array_key_exists($routeName, $this->_routes_strict_override)
            && array_key_exists($routeName, $this->routesAdded)
        )
            throw new \RuntimeException(sprintf(
                'Router with name "%s" exists.'
                , $router->getName()
            ));

        return $router;
    }
}
