<?php
namespace Poirot\Router\Interfaces\Http;

use Poirot\Core\Interfaces\iPoirotEntity;
use Poirot\Core\Interfaces\OptionsProviderInterface;
use Poirot\Http\Interfaces\Message\iHttpRequest;

interface iHRouter
    extends OptionsProviderInterface
{
    /**
     * Construct
     *
     * @param string $name    Router Name
     * @param array  $options Router Options, like Uri, etc ..
     * @param array  $params  Default Params
     */
    function __construct($name, $options = null, $params = null);

    /**
     * Get Router Name
     *
     * @return string
     */
    function getName();

    /**
     * Is Match with Request
     *
     * @param iHttpRequest $request
     *
     * @return bool
     */
    function isMatch(iHttpRequest $request);

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
