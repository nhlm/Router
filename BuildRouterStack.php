<?php
namespace Poirot\Router;

use Poirot\Router\Route\RouteStackChainWrapper;
use Poirot\Router\Interfaces\iRoute;
use Poirot\Router\Interfaces\iRouterStack;

use Poirot\Std\ConfigurableSetter;


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
        foreach($this->routes as $routeName => $routeValuable)
        {
            $route = $routeValuable;

            if (is_string($routeName) && is_array($routeValuable)) {
                // when route provided as array options
                $route = $this->_attainRouteFromArray($routeName, $routeValuable);
                $override = (isset($routeValuable['override']))  ? $routeValuable['override'] : null;
            }

            if (!$route instanceof  iRoute)
                throw new \InvalidArgumentException(sprintf(
                    'Invalid argument provided. ("%s")'
                    , \Poirot\Std\flatten($routeValuable)
                ));

            # add router
            if (isset($override))
                ## just if override option provided
                $router->add($route, $override);
            else
                ## using default value
                $router->add($route);
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
     * @param string $routeName
     * @param array  $routeValuable
     *
     * @return iRoute|iRouterStack
     */
    protected function _attainRouteFromArray($routeName, array $routeValuable)
    {
        if (! (isset($routeValuable['route']) || isset($routeValuable['routes'])) )
            throw new \InvalidArgumentException(
                'Options must define requested route as options key on "route" or "routes".'
            );

        $route = null;
        if ( isset($routeValuable['route']) ) {
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

            /** @var iRoute|iRouterStack $route */
            $route = new $routeClass($routeName);

            $options  = (isset($routeValuable['options']))   ? $routeValuable['options']  : null;
            $params   = (isset($routeValuable['params']))    ? $routeValuable['params']   : null;

            ($options === null) ?: $route->with($route::parseWith($options));
            ($params === null)  ?: $route->params()->import($params);
        }

        $routes   = (isset($routeValuable['routes']))    ? $routeValuable['routes']   : null;
        if ($routes && !$route instanceof iRouterStack) {
            // it has child routes
            if ($route === null)
                $route = new RouterStack($routeName);
            else
                $route = new RouteStackChainWrapper($route);

            $build = new self();
            $build->setRoutes($routes);
            $build->build($route);
        }

        return $route;
    }
}
