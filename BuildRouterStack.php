<?php
namespace Poirot\Router;

use Poirot\Router\Route\RouteStackChainDecorate;
use Poirot\Std\ConfigurableSetter;

use Poirot\Router\Interfaces\iRoute;
use Poirot\Router\Interfaces\iRouterStack;

class BuildRouterStack
    extends ConfigurableSetter
{
    /** @var array */
    protected $routes = array();
    
    /**
     * Build Router Stack 
     * @param iRouterStack $router
     */
    function build(iRouterStack $router)
    {
        foreach($this->routes as $route => $valuable) 
        {
            if (is_string($route) && is_array($valuable))
                $this->_addRouteFromArray($router, $route, $valuable);
            elseif (is_int($route) && $valuable instanceof iRoute)
                $this->_addRouteInstance($router, $valuable);
            else
                throw new \InvalidArgumentException(sprintf(
                    'Invalid argument provided. ("%s")'
                    , \Poirot\Std\flatten($valuable)
                ));
        }
    }
    
    /**
     * Add routes
     *
     * $routes: 
     * [
     *   'pages' => [ # Route Name
     *      // Routes class that not exists prefixed with namespace
     *      // exp. RouteSegment --> \Poirot\Router\Route\RouteSegment
     *      'route'          => '\RouteClass',
     *      
     *      'allow_override' => true,
     *       
     *      'options'   => ..,
     *      'params'    => ..default route params,
     *      
     *      ## Child Nested Routes
     *      'routes'         => [
     *         iRoute,
     *         'route_name' => [ ..options]
     *      ],
     *   ],
     * ]
     *
     * @param array $routes
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    function setRoutes(array $routes)
    {
        $this->routes = $routes;
        return $this;
    }
    
    
    // ..

    /**
     * @param iRouterStack $router
     * @param string       $routeName
     * @param array        $routeValuable
     */
    protected function _addRouteFromArray($router, $routeName, array $routeValuable)
    {
        if (!isset($routeValuable['route']))
            throw new \InvalidArgumentException(
                'Options must define requested route as options key on "route".'
            );

        
        $routeClass = (string) $routeValuable['route'];
        if (!class_exists($routeClass)) {
            // prefixed with namesapce looking for default routes
            $routeClass = ltrim($routeClass, '\\');
            $routeClass = __NAMESPACE__.'\\Route\\'.$routeClass;
        }

        if (!class_exists($routeClass))
            throw new \InvalidArgumentException(sprintf(
                'Router (%s) not found.'
                , $routeValuable['route']
            ));

        $options  = (isset($routeValuable['options']))   ? $routeValuable['options']  : null;
        $params   = (isset($routeValuable['params']))    ? $routeValuable['params']   : null;
        $override = (isset($routeValuable['override']))  ? $routeValuable['override'] : null;
        $routes   = (isset($routeValuable['routes']))    ? $routeValuable['routes']   : null;
        if ($routes && !$router instanceof iRouterStack) {
            // it has child routes
            $router = new RouteStackChainDecorate($router);
            ## add child routes, so we sure about ChainRouter after add()::recent method
            $build = new self($routes);
            $build->build($router);
        }

        /** @var iRoute|iRouterStack $route */
        $route = new $routeClass($routeName);
        ($options === null) ?: $route->with($route::parseWith($options));
        ($params === null)  ?: $route->params()->import($params);

        # add router
        if ($override !== null)
            ## just if override option provided
            $router->add($route, $override);
        else
            ## using default value
            $router->add($route);
    }

    /**
     * @param iRouterStack $router
     * @param iRoute       $route
     */
    protected function _addRouteInstance($router, iRoute $route)
    {
        $router->add($route);
    }
}
