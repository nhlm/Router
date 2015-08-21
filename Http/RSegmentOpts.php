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
     * Path Offset To Match Criteria After
     *
     * @var array[start, end]
     */
    protected $pathOffset = null;

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

    /**
     * Set Path Offset
     *
     * @param int|array|null $pathOffset
     */
    function setPathOffset($pathOffset)
    {
        if (is_int($pathOffset))
            $pathOffset = [$pathOffset, null];

        $this->pathOffset = $pathOffset;
    }

    /**
     * Get Path Offset
     *
     * @return array|null
     */
    function getPathOffset()
    {
        return $this->pathOffset;
    }
}
