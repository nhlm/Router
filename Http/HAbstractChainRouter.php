<?php
namespace Poirot\Router\Http;

use Poirot\Core\AbstractOptions;
use Poirot\Core\Entity;
use Poirot\Core\Interfaces\iPoirotOptions;
use Poirot\Core\OpenOptions;
use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;
use Poirot\Router\Interfaces\Http\iHChainingRouter;
use Poirot\Router\Interfaces\Http\iHRouter;

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
class HAbstractChainRouter extends HAbstractRouter
    implements iHChainingRouter
{
    const SEPARATOR_NAME = '/';

    /**
     * @var string Router Name
     */
    protected $name;

    /**
     * Nest Right Link
     * @var iHChainingRouter
     */
    protected $_leafRight;

    /**
     * Parallel Routers
     *
     * @var array[iHChainingRouter]
     */
    protected $_parallelRouters = [];

    /**
     * Parent Leaf for linked routers
     * @var HChainWrapper
     */
    protected $_leafToParent = null;

    /**
     * Recent added router
     * @var HChainWrapper
     */
    protected $_recentRouter;

    /**
     * @var Entity Router Params
     */
    protected $params;

    /**
     * @var OpenOptions|iPoirotOptions
     */
    protected $options;

    /**
     * Get Router Name
     *
     * note: Name injected on add/link routers
     * @see HChainWrapper::__prepareRouter()
     *
     * @return string
     */
    function getName()
    {
        ## prepend parent as namespace to this route
        $name = ($this->_leafToParent) ? $this->_leafToParent->getName() : '';
        $name = ($name) ? $name.self::SEPARATOR_NAME.$this->name : $this->name;

        return $name;
    }

    /**
     * Match with Request
     *
     * - merge with current params
     *
     * - manipulate params on match
     *   exp. when match host it contain host param
     *   with matched value
     *
     * @param iHttpRequest $request
     *
     * @return iHRouter|false
     */
    function match(iHttpRequest $request)
    {
        ## then match against connected routers if exists
        if (!$this->_leafRight && empty($this->_parallelRouters))
            return false;

        # build queue list for routers to match:
        $routers     = $this->_parallelRouters;
        ## right leaf link check for match first
        (empty($this->_leafRight)) ?: array_unshift($routers, $this->_leafRight);

        # match routes:
        $routerMatch = false;
        /** @var iHChainingRouter $r */
        foreach($routers as $r)
            if ($routerMatch = $r->match($request))
                break;

        if ($routerMatch)
            $routerMatch->params()->from(\Poirot\Core\array_merge_recursive(
                $this->params()->toArray()
                , $routerMatch->params()->toArray()
            ));

        return $routerMatch;
    }

    /**
     * Explore Router With Name
     *
     * - route name must start with self router name
     * - the names separated by "/"
     *
     * @param string $routeName
     *
     * @return iHRouter|false
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
        $nestRoutes = $this->_parallelRouters;
        if ($this->_leafRight)
            ## prepend linked router for first check
            array_unshift($nestRoutes, $this->_leafRight);

        /** @var iHChainingRouter $nr */
        foreach($nestRoutes as $nr)
            if ($return = $nr->explore($routeName))
                return $return;

        return false;
    }

    /**
     * Set Nest Link To Next Router
     *
     * - set self as parent of linked router
     * - prepend current name to linked router name
     *
     * @param iHChainingRouter|iHRouter $router
     *
     * @return $this
     */
    function link(/*iHRouter*/ $router)
    {
        $router = $this->__prepareRouter($router);

        $this->_leafRight = $router;

        $this->_recentRouter = $router;

        return $this;
    }

    /**
     * Add Parallel Router
     *
     * - set self as parent of linked router
     * - prepend current name to linked router name
     *
     * @param iHChainingRouter|iHRouter $router
     *
     * @return $this
     */
    function add(/*iHRouter*/ $router)
    {
        $router = $this->__prepareRouter($router);
        $this->_parallelRouters[$router->getName()] = $router;

        $this->_recentRouter = $router;

        return $this;
    }

    /**
     * Recent Chained Router
     *
     * @return HAbstractChainRouter|HChainWrapper
     */
    function recent()
    {
        if (!$this->_recentRouter)
            $this->_recentRouter = $this;

        return $this->_recentRouter;
    }

    /**
     * Get Parent Chain Leaf
     *
     * @return false|iHChainingRouter
     */
    function parent()
    {
        return $this->_leafToParent;
    }

        protected function __prepareRouter($router)
        {
            if (!$router instanceof iHRouter)
                throw new \InvalidArgumentException(sprintf(
                    'Router must instance of "iHRouter", "%s" given.'
                    , is_object($router) ? get_class($router) : gettype($router)
                ));

            if (!$router instanceof iHChainingRouter)
                ## chained router must be chaining type
                $router = new HChainWrapper($router);

            # set self as parent of linked router
            $router->_leafToParent = $this;

            ## check if router name exists
            if (array_key_exists($router->getName(), $this->_parallelRouters)
                || ($this->_leafRight && $this->_leafRight->getName() === $router->getName())
            )
                throw new \RuntimeException(sprintf(
                    'Router with name "%s" exists.'
                    , $router->getName()
                ));

            return $router;
        }

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return HttpUri
     */
    function assemble(array $params = [])
    {
        ## Chaining Connectors does`nt assemble anything
        return new HttpUri();
    }
}
