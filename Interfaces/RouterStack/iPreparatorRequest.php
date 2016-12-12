<?php
namespace Poirot\Router\Interfaces\RouterStack;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

interface iPreparatorRequest
{
    /**
     * Prepare Request Object
     *
     * @param RequestInterface $request
     *
     * @return RequestInterface Clone
     */
    function withRequestOnMatch(RequestInterface $request);

    /**
     *
     *
     * @param UriInterface $uri
     *
     * @return UriInterface
     */
    function withUriOnAssemble(UriInterface $uri);
}
