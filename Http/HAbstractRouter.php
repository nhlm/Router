<?php
namespace Poirot\Router\Http;

use Poirot\Core\AbstractOptions;
use Poirot\Core\Entity;
use Poirot\Core\Interfaces\iOptionImplement;
use Poirot\Core\Interfaces\iPoirotEntity;
use Poirot\Core\Interfaces\iPoirotOptions;
use Poirot\Core\OpenOptions;
use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;
use Poirot\Router\Interfaces\Http\iHRouter;

abstract class HAbstractRouter implements iHRouter
{
    /**
     * @var string Router Name
     */
    protected $name;

    /**
     * @var Entity Router Params
     */
    protected $params;

    /**
     * @var OpenOptions|iPoirotOptions
     */
    protected $options;

    /**
     * Create a new route with given options
     *
     * options: [
     *  'name'    => 'RouterPartName',
     *  'options' => [], # iOptionImplement
     *  'params'  => [], # iPoirotEntity
     * ]
     *
     * @param array $factArr Builder Factory Config
     *
     * @throws \InvalidArgumentException
     * @return iHRouter
     */
    static function factory(array $factArr)
    {
        if(!array_key_exists('name', $factArr))
            throw new \InvalidArgumentException(
                'Require route name as option: "[\'name\' => \'RouteName\']"'
            );

        $options = (isset($factArr['options'])) ? $factArr['options'] : [];
        $params  = (isset($factArr['params']))  ? $factArr['params']  : [];

        return new static($factArr['name'], $options, $params);
    }

    /**
     * Construct
     *
     * @param string $name Router Name
     * @param array|iOptionImplement $options Router Options, like Uri, etc ..
     * @param array $params Default Params
     */
    function __construct($name, $options = null, $params = null)
    {
        $this->name = $name;

        if ($options !== null)
            $this->options()->from($options);

        if ($params !== null)
            $this->params()->from($params);
    }

    /**
     * Get Router Name
     *
     * @return string
     */
    function getName()
    {
        return $this->name;
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
    abstract function match(iHttpRequest $request);

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return HttpUri
     */
    abstract function assemble(array $params = []);

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
            $this->options = static::optionsIns();

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
