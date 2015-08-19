<?php
namespace Poirot\Router\Http;

use Poirot\Core\AbstractOptions;
use Poirot\Core\Entity;
use Poirot\Core\Interfaces\iPoirotEntity;
use Poirot\Core\Interfaces\iPoirotOptions;
use Poirot\Core\OpenOptions;
use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;
use Poirot\Router\Interfaces\Http\iHChainingRouter;
use Poirot\Router\Interfaces\Http\iHRouter;

class HChainWrapper implements iHChainingRouter
{
    const SEPARATOR_NAME = '/';

    /**
     * @var iHRouter
     */
    protected $_resourceRouter;

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
     * @var Entity Router Params
     */
    protected $params;

    /**
     * @var OpenOptions|iPoirotOptions
     */
    protected $options;

    /**
     * Construct
     *
     * @param iHRouter $router Wrapper around router
     */
    function __construct($router)
    {
        $this->_resourceRouter = $router;
    }

    /**
     * Get Router Name
     *
     * @return string
     */
    function getName()
    {
        $name = $this->_resourceRouter->getName();

        // TODO apply resource route name

        return $name;
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
        // TODO check for equal naming on routers

        $router = $this->__prepareRouter($router);

        $this->_parallelRouters[] = $router;

        return $this;
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

            # prepend current name to linked router:
            $router->name = $this->getName().self::SEPARATOR_NAME.$router->getName();

            # set self as parent of linked router
            $router->_leafToParent = $this;

            return $router;
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
        $routerMatch = false;

        $wrappedMatch = $this->_resourceRouter->match($request);
        if ($wrappedMatch) {
            ## return self as matched router
            $routerMatch = clone $this;
            $params      = $wrappedMatch->params();

            ## then match against connected routers if exists
            if ($this->_leafRight || !empty($this->_parallelRouters)) {
                $routerMatch = $this->__matchConnectedRouters($request);
                $params      = array_merge_recursive(
                    $params->toArray()
                    , $routerMatch->params()->toArray()
                );
            }

            $routerMatch->params()->from($params);
        }

        return $routerMatch;
    }

        function __matchConnectedRouters($request)
        {
            # build queue list for routers to match:
            ### first self class as match
            $routers = [];
            ### then right leaf link
            (empty($this->_leafRight)) ?: array_push($routers, $this->_leafRight);
            ### then parallel routers
            if ($this->_parallelRouters) foreach($this->_parallelRouters as $r)
                    array_push($routers, $r);

            # match routes:
            $routerMatch = false;
            /** @var iHChainingRouter $r */
            foreach($routers as $r)
                if ($routerMatch = $r->match($request))
                    break;

            return $routerMatch;
        }

    /**
     * Explore Router With Name
     *
     * @param string $routeName
     *
     * @return iHRouter|false
     */
    function explore($routeName)
    {
        // TODO: Implement explore() method.
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
        # first assemble from wrapped resource router
        $httpUri = $this->_resourceRouter->assemble($params);
        if ($this->_leafToParent)
            ## merge with parent leaf assembled properties
            $httpUri->from($this->_leafToParent->assemble($params));

        return $httpUri;
    }

    /**
     * Route Params
     *
     * @return iPoirotEntity
     */
    function params()
    {
        if (!$this->params)
            $this->params = new Entity;

        return $this->params;
    }

    /**
     * @return AbstractOptions
     */
    function options()
    {
        if (!$this->options)
            $this->options = self::optionsIns();

        return $this->options;
    }

    /**
     * Get An Bare Options Instance
     *
     * ! it used on easy access to options instance
     *   before constructing class
     *   [php]
     *      $opt = Filesystem::optionsIns();
     *      $opt->setSomeOption('value');
     *
     *      $class = new Filesystem($opt);
     *   [/php]
     *
     * @return AbstractOptions
     */
    static function optionsIns()
    {
        return new OpenOptions;
    }
}
 