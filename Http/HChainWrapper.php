<?php
namespace Poirot\Router\Http;

use Poirot\Core\AbstractOptions;
use Poirot\Core\Interfaces\iPoirotEntity;
use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;
use Poirot\Router\Interfaces\Http\iHRouter;

class HChainWrapper extends HAbstractChainRouter
{
    /**
     * @var iHRouter
     */
    protected $_resourceRouter;

    /**
     * Construct
     *
     * @param iHRouter $router Wrapper around router
     */
    function __construct($router)
    {
        $this->_resourceRouter = $router;
        $this->name = $router->getName();
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
        # first must match with wrapped router
        $routerMatch = $this->_resourceRouter->match($request);
        if ($routerMatch)
            $routerMatch = clone $this;

        ## then match against connected routers if exists
        if ($this->_leafRight || !empty($this->_parallelRouters))
            $routerMatch = parent::match($request);

        return $routerMatch;
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
            $this->params = $this->_resourceRouter->params();

        return $this->params;
    }

    /**
     * @return AbstractOptions
     */
    function options()
    {
        if (!$this->options)
            $this->options = $this->_resourceRouter->options();

        return $this->options;
    }
}
