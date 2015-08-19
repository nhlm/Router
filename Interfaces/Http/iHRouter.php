<?php
namespace Poirot\Router\Interfaces\Http;

use Poirot\Core\Interfaces\iPoirotEntity;
use Poirot\Core\Interfaces\OptionsProviderInterface;
use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;

interface iHRouter
    extends OptionsProviderInterface
{
    /**
     * Construct
     *
     * @param string $name Router Name
     */
    function __construct($name);

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
     * @return HttpUri
     */
    function assemble(array $params = []);
}
