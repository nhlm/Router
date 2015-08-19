<?php
namespace Poirot\Router\Http;

use Poirot\Core\AbstractOptions;

class RSchemeOpts extends AbstractOptions
{
    /**
     * @var string
     */
    protected $scheme = 'http';

    /**
     * Set Scheme
     *
     * @param string $scheme
     *
     * @return $this
     */
    function setScheme($scheme)
    {
        $this->scheme = $scheme;
    }

    /**
     * Get Scheme
     *
     * @return string
     */
    function getScheme()
    {
        return $this->scheme;
    }
}
