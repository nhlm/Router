<?php
namespace Poirot\Router\Http;

use Poirot\Container\ContainerBuilder;
use Poirot\Container\Exception\ContainerInvalidPluginException;
use Poirot\Container\Interfaces\iContainerBuilder;
use Poirot\Container\Plugins\AbstractPlugins;
use Poirot\Router\Interfaces\Http\iHRouter;

class RChainStackPlugins extends AbstractPlugins
{
    /**
     * Construct
     *
     * @param iContainerBuilder $cBuilder
     *
     * @throws \Exception
     */
    function __construct(iContainerBuilder $cBuilder = null)
    {
        parent::__construct($cBuilder);

        $this->__buildWithDefaults();
    }

    /**
     * Validate Plugin Instance Object
     *
     * @param mixed $pluginInstance
     *
     * @throws ContainerInvalidPluginException
     * @return void
     */
    function validatePlugin($pluginInstance)
    {
        if (!$pluginInstance instanceof iHRouter)
            throw new ContainerInvalidPluginException(sprintf(
                'Routes must instance of "iHRouter" but "%s" given.'
                , get_class($pluginInstance)
            ));
    }

    protected function __buildWithDefaults()
    {
        $defaults = [
            'services'   => [
                'segment' => [
                    '_class_' => 'FunctorService',
                    'callable' => function($name, $options = [], $params = []) {
                        return new RSegment($name, $options, $params);
                    },
                ],
                'host' => [
                    '_class_' => 'FunctorService',
                    'callable' => function($name, $options = [], $params = []) {
                        return new RHostname($name, $options, $params);
                    },
                ],
                'scheme' => [
                    '_class_' => 'FunctorService',
                    'callable' => function($name, $options = [], $params = []) {
                        return new RScheme($name, $options, $params);
                    },
                ],
                'chainstack' => [
                    '_class_' => 'FunctorService',
                    'callable' => function($name, $options = [], $params = []) {
                        return new RChainStack($name, $options, $params);
                    },
                ],
            ],
        ];

        (new ContainerBuilder($defaults))->build($this);
    }
}
