<?php
namespace Poirot\Router\Http;

use Poirot\Core\AbstractOptions;

class RSegmentOpts extends AbstractOptions
{
    /**
     * @var string
     */
    protected $criteria;

    /**
     * Set Criteria
     *
     * criteria can be one of the following:
     *
     * - '/en' or '/about'
     * - Regex Definition as params
     *   ['/:locale' => ['locale' => '\w{2}'] ...]
     *
     * @param array|string $criteria
     *
     * @return $this
     */
    function setCriteria($criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * Get Criteria
     *
     * @return string
     */
    function getCriteria()
    {
        return $this->criteria;
    }
}
