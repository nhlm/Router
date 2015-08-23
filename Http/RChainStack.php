<?php
namespace Poirot\Router\Http;

use Poirot\Router\Interfaces\Http\iHRouter;

class RChainStack extends HAbstractChainRouter
{
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

    }

    protected function __addInstanceRoute(iHRouter $route)
    {

    }
}
