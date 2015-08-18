<?php
namespace Poirot\Router\Interfaces\Http;

use Poirot\Core\Interfaces\iOptionImplement;
use Poirot\Core\Interfaces\iPoirotEntity;
use Poirot\Core\Interfaces\OptionsProviderInterface;
use Poirot\Http\Interfaces\Message\iHttpRequest;

interface iHRouter
    extends OptionsProviderInterface
{
    /**
     * Construct
     *
     * @param string                  $name    Router Name
     * @param array|iOptionImplement  $options Router Options, like Uri, etc ..
     * @param array                   $params  Default Params
     */
    function __construct($name, $options = null, $params = null);

    /**
     * Get Router Name
     *
     * @return string
     */
    function getName();

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
    function match(iHttpRequest $request);

    /**
     * Route Params
     *
     * @return iPoirotEntity
     */
    function params();

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return string
     */
    function assemble(array $params = []);
}
