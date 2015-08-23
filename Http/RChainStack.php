<?php
namespace Poirot\Router\Http;

use Poirot\Container\Interfaces\Plugins\iPluginManagerAware;
use Poirot\Container\Interfaces\Plugins\iPluginManagerProvider;
use Poirot\Container\Plugins\AbstractPlugins;
use Poirot\Router\Interfaces\Http\iHRouter;

class RChainStack extends HAbstractChainRouter
    implements iPluginManagerAware
    , iPluginManagerProvider
{
    /**
     * @var AbstractPlugins
     */
    protected $routesAsPlugin;

    /**
     * @inheritdoc
     *
     * @param array $factArr Builder Factory Config
     *
     * @throws \InvalidArgumentException
     * @return iHRouter
     */
    static function factory(array $factArr)
    {
        /** @var RChainStack $instance */
        $instance = parent::factory($factArr);

        if (isset($factArr['routes']))
            $instance->addRoutes($factArr['routes']);

        return $instance;
    }

    /**
     * Add routes
     *
     * ! routes: [
     *      // RSegment::factory([ ...
     *      ## or
     *      'pages' => [ ## route name
     *         'route' => 'segment', ## route instance as service name
     *          ## ...
     *         'options' => [],
     *         'params'  => [],
     *          ## add child routes
     *         'routes' => [
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
                $this->__addAssocRoute($rn, $ro);
            elseif (is_int($rn) && $ro instanceof iHRouter)
                $this->__addInstanceRoute($ro);
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
     *         'route' => 'segment', ## route instance as service name
     *          ## ...
     *         'options' => [],
     *   ...
     *
     * @param string $routeName
     * @param array  $options
     */
    protected function __addAssocRoute($routeName, array $options)
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

        $routes  = (isset($options['routes']))  ? $options['routes']  : [];
        $options = (isset($options['options'])) ? $options['options'] : [];
        $params  = (isset($options['params']))  ? $options['params']  : [];
        $router  = $this->getPluginManager()->get($routeType, [$routeName, $options, $params]);

        # add router
        $this->add($router)
            ## add child routes, so we sure about ChainRouter after add()::recent method
            ->recent()->addRoutes($routes);
    }

    protected function __addInstanceRoute(iHRouter $route)
    {
        $this->add($route);
    }

    /**
     * Set Plugins Manager
     *
     * @param AbstractPlugins $plugins
     *
     * @return $this
     */
    function setPluginManager(AbstractPlugins $plugins)
    {
        $this->routesAsPlugin = $plugins;

        return $this;
    }

    /**
     * Get Plugins Manager
     *
     * @return AbstractPlugins
     */
    function getPluginManager()
    {
        if(!$this->routesAsPlugin)
            return $this->routesAsPlugin = new RChainStackPlugins;

        # default plugin routes
        return $this->routesAsPlugin;
    }
}
