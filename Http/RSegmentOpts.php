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
     * Check for exact match
     *
     * exp. when false: "/pages/about" <= match with request "/pages"
     *      when true only match with "/pages/about"
     *
     * @var bool
     */
    protected $exactMatch = true;

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

        return $this;
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
     *
     * @return $this
     */
    function setPathOffset($pathOffset)
    {
        if (is_int($pathOffset))
            $pathOffset = [$pathOffset, null];

        $this->pathOffset = $pathOffset;

        $this->setExactMatch(false);

        return $this;
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

    /**
     * Set Exact match flag
     *
     * exp. when false => /pages/about <= match with request /pages
     *      when true only match with /pages/about
     *
     * @param boolean $exactMatch
     *
     * @return $this
     */
    function setExactMatch($exactMatch)
    {
        $this->exactMatch = $exactMatch;

        return $this;
    }

    /**
     * Get Exact match
     *
     * @return boolean
     */
    function getExactMatch()
    {
        return $this->exactMatch;
    }
}
