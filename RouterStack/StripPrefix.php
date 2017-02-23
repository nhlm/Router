<?php
namespace Poirot\Router\RouterStack;

use Poirot\Router\Interfaces\RouterStack\iPreparatorRequest;
use Poirot\Std\Type\StdString;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;


class StripPrefix
    implements iPreparatorRequest
{
    /** @var string */
    protected $_stripPrefix;


    /**
     * StripPrefix constructor.
     * @param string $prefixStrip
     */
    function __construct($prefixStrip)
    {
        $this->_stripPrefix = (string) $prefixStrip;
    }

    /**
     * Prepare Request Object
     *
     * @param RequestInterface $request
     *
     * @return RequestInterface Clone
     */
    function withRequestOnMatch(RequestInterface $request)
    {
        $requestTarget = $request->getRequestTarget();
        $requestTarget = new StdString($requestTarget);
        $requestTarget = $requestTarget->stripPrefix( rtrim($this->_stripPrefix, '/') );
        $request       = $request->withRequestTarget( (string) $requestTarget );

        return $request;
    }

    /**
     *
     *
     * @param UriInterface $uri
     *
     * @return UriInterface
     */
    function withUriOnAssemble(UriInterface $uri)
    {
        $uri  = \Poirot\Psr7\modifyUri($uri, array(
            'path' => $this->_stripPrefix,
        ), \Poirot\Psr7\URI_PREPEND);

        return $uri;
    }
}
