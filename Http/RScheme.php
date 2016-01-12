<?php
namespace Poirot\Router\Http;

use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;
use Poirot\Router\Interfaces\Http\iHRouter;

class RScheme extends HAbstractRouter
{
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
    function match(iHttpRequest $request)
    {
        $uri    = $request->getUri();
        $scheme = $uri->getScheme();

        if ($scheme !== $this->inOptions()->getScheme())
            return false;

        $routeMatch = clone $this;
        return $routeMatch;
    }

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return HttpUri
     */
    function assemble(array $params = [])
    {
        $uri = (new HttpUri())->setScheme(
            $this->inOptions()->getScheme()
        );

        return $uri;
    }

    /**
     * !! just for IDE auto completion integration
     *
     * @return RSchemeOpts
     */
    function inOptions()
    {
        return parent::inOptions();
    }

    /**
     * @inheritdoc
     * @return RSchemeOpts
     */
    static function newOptions()
    {
        return new RSchemeOpts;
    }
}
