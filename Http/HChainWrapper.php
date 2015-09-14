<?php
namespace Poirot\Router\Http;

use Poirot\Core\AbstractOptions;
use Poirot\Core\Interfaces\iPoirotEntity;
use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;
use Poirot\Router\Interfaces\Http\iHRouter;

class HChainWrapper extends RChainStack
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
        $wrapperMatch = call_user_func_array(
            [$this->_resourceRouter, 'match'], func_get_args()
        ); ## ->match($request);

        if (!$wrapperMatch)
            return false;

        $routerMatch = clone $this;
        $routerMatch->params()->merge($wrapperMatch->params());

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

        if ($this->_leafToParent) {
            ## merge with parent leaf assembled properties
            $parentUri = $this->_leafToParent->assemble($params);
            $parentUri = $parentUri->toArray();

            if (isset($parentUri['path'])) {
                ### paths must prepend to uri
                $httpUri->getPath()->prepend($parentUri['path']);
                unset($parentUri['path']);
            }
            if (isset($parentUri['query'])) {
                ### query strings must merged
                $httpUri->getQuery()->merge($parentUri['query']);
                unset($parentUri['query']);
            }

            ### all going replaced
            if(!empty($parentUri))
                $httpUri->from($parentUri);
        }

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
