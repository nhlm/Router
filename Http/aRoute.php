<?php
namespace Poirot\Router\Http;

use Poirot\Router\Interfaces\iRoute;
use Poirot\Std\ConfigurableSetter;
use Poirot\Std\Interfaces\Struct\iData;
use Poirot\Std\Struct\DataEntity;
use Poirot\Std\Struct\DataOptionsOpen;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

abstract class aRoute 
    extends ConfigurableSetter
    implements iRoute
{
    /** @var string Router Name */
    protected $name;

    /** @var DataEntity Router Params */
    protected $params;

    /** @var DataOptionsOpen */
    protected $options;
    
    /**
     * Construct
     *
     * @param string             $name    Router Name
     * @param array|\Traversable $options Router Options, like Uri, etc ..
     * @param array|\Traversable $params  Default Params
     */
    function __construct($name, $options = null, $params = null)
    {
        $this->name = $name;
        parent::__construct($options);
        
        if ($params !== null)
            $this->params()->import($params);
    }

    /**
     * @override Ensure not throw exception
     * @inheritdoc
     */
    function with($options, $throwException = false)
    {
        parent::with($options);
    }
    
    /**
     * Match with Request
     *
     * - on match extract request params and merge
     *   into default params
     *
     * !! don`t change request object attributes
     *
     * @param RequestInterface $request
     *
     * @return iRoute|false
     */
    abstract function match(RequestInterface $request);

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return UriInterface
     */
    abstract function assemble(array $params = array());
    
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
     * Route Default Params
     *
     * @return DataEntity|iData
     */
    function params()
    {
        if (!$this->params)
            $this->params = new DataEntity;

        return $this->params;
    }

    /**
     * Helper to set Params with Setter Builder
     * @param array|\Traversable $params
     */
    protected function setParams($params)
    {
        $this->params()->import($params);
    }
}
