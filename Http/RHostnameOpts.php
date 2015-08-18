<?php
namespace Poirot\Router\Http;

use Poirot\Core\AbstractOptions;

class RHostnameOpts extends AbstractOptions
{
    /**
     * @var string|array
     */
    protected $hostCriteria;

    /**
     * Set Criteria
     *
     * criteria can be one of the following:
     *
     * - 'mysite.com' or 'localhost' or 'sb.site.tld'
     * - Regex Definition as params
     *   [':subDomain.site.com' => ['subDomain' => 'fw\d{2}'] ]
     *
     * @param array|string $hostCriteria
     */
    function setHostCriteria($hostCriteria)
    {
        $this->hostCriteria = $hostCriteria;
    }

    /**
     * Get Criteria
     *
     * @return array|string
     */
    function getHostCriteria()
    {
        return $this->hostCriteria;
    }
}
