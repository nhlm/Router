<?php
namespace Poirot\Router\Route;

use Poirot\Router\RouterChain;
use Poirot\Router\Interfaces\iRoute;

class RouterStack 
    extends RouterChain
{
    /**
     * Add routes
     *
     * ! routes: [
     *      // RSegment::factory([ ...
     *      ## or
     *      'pages' => [ ## route name
     *         'route'    => 'segment', ## route instance as service name
     *         'override' => true,      ## allow override
     *          ## ...
     *         'options'  => [],
     *         'params'   => [],
     *          ## add child routes
     *         'routes'   => [
     *              RSegment::factory([
     *                   'name' => 'page', ## route name "pages/page"
     *                    ## ...
     *                   'options' => [],
     *                   'params'  => [],
     *              ]),
     *          ],
     *     ], // end pages route
     *   ]
     *
     * @param array $routes
     *
     * @throws \InvalidArgumentException
     * @return $this
     */
    function addRoutes(array $routes)
    {
        foreach($routes as $rn => $ro) {
            if (is_string($rn) && is_array($ro))
                $this->_addRouteFromArray($rn, $ro);
            elseif (is_int($rn) && $ro instanceof iRoute)
                $this->_addRouteInstance($ro);
            else
                throw new \InvalidArgumentException(sprintf(
                    'Invalid argument provided. ("%s")'
                    , serialize($ro)
                ));
        }

        return $this;
    }

    /**
     * - if link leaf is empty add route by link
     *
     * : 'pages' => [ ## route name
     *         'route'    => 'segment', ## route instance as service name
     *         'override' => true,      ## allow override
     *          ## ...
     *         'options'  => [],
     *   ...
     *
     * @param string $routeName
     * @param array  $options
     */
    protected function _addRouteFromArray($routeName, array $options)
    {
        if (!isset($options['route']))
            throw new \InvalidArgumentException(
                'Options must define requested route as options key on "route".'
            );

        $routeType = $options['route'];
        if (!$this->getPluginManager()->has($routeType))
            throw new \InvalidArgumentException(sprintf(
                'Router "%s" not found on container.'
                , $routeType
            ));

        $routes   = (isset($options['routes']))    ? $options['routes']   : array();
        $opts     = (isset($options['options']))   ? $options['options']  : array();
        $params   = (isset($options['params']))    ? $options['params']   : array();
        $override = (isset($options['override']))  ? $options['override'] : null;

        $router  = $this->getPluginManager()->fresh($routeType, array($routeName, $opts, $params));

        # add router
        if ($override !== null)
            ## just if override option provided
            $this->add($router, $override);
        else
            ## using default value
            $this->add($router);

            ## add child routes, so we sure about ChainRouter after add()::recent method
        $this->recent()->addRoutes($routes);
    }

    protected function _addRouteInstance(iRoute $route)
    {
        $this->add($route);
    }
}
