<?php
namespace Poirot\Router\Route;

/*
 * Match Uri segment against criteria
 *
 * [code]
 * new RSegment('page',
 *       [
 *         ## options
 *           ### request_page defined as parameter and will merged with def. params on success match
 *           'criteria'    => ':request_page{{\w+}}',
 *           'criteria'    => '/path/uri/to/match',
 *           ### match exact or just current part of criteria with given request uri
 *           'match_whole' => false,
 *       ]
 *         ## params
 *       , ['__action__' => 'display_page']
 *   )
 * [/code]
 *
 * TODO refactor codes
 */
use Poirot\Psr7\Uri;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

use Poirot\Router\aRoute;
use Poirot\Router\Interfaces\iRoute;

class RouteSegment 
    extends aRoute
{
    /**
     * Translation keys used in the regex.
     * @var array
     */
    protected $_translationKeys = array();


    // Options:

    /** @var string */
    protected $criteria;

    /**
     * Check for exact match
     * exp. when false: "/pages/about" <= match with request "/pages"
     *      when true only match with "/pages/about"
     *
     * @var bool
     */
    protected $exactMatch = true;

    /**
     * Path Offset To Match Criteria After
     * @var array[start, end]
     */
    protected $pathOffset = null;
    

    /**
     * Match with Request
     *
     * - merge with current params
     * - manipulate params on match
     *   exp. when match host it contain host param
     *   with matched value
     *
     * @param RequestInterface $request
     *
     * @return RouteSegment|iRoute|false usually clone/copy of matched route
     */
    function match(RequestInterface $request)
    {
        $criteria = $this->getCriteria();
        $criteria = ($criteria == '/') ? '' : $criteria;

        # match criteria:
        $parts = \Poirot\Std\Lexer\parseCriteria($criteria);
        $regex = \Poirot\Std\Lexer\buildRegexFromParsed($parts);
        $regex = ($this->isMatchWhole())
            ? "(^{$regex}/$)" ## exact match
            // TODO /me match on /members request with matchWhole Option false
            //      so i add trailing slash but not tested completely
            : "(^{$regex}/)"; ## only start with criteria "/pages[/other/paths]"


        $path = rtrim($request->getRequestTarget(), '/');

        $path = parse_url($path);
        if (isset($path['path']))
            $path = $path['path'];
        else 
            $path = '';

        if ($path === '') $path = '/';

        $pathOffset = $this->getSegment();
        if ($pathOffset !== null) {
            // split path into given offset
            // according to rules on uri slice @see UriSequence::split
            $path = explode('/', $path);

            $path = array_slice($path, $pathOffset[0], $pathOffset[1]);
            $path = implode('/', $path);
            if ($path == '')
                $path =  '/';
        }

        // @see line 83 for added trailing slash '/'
        $result = preg_match($regex, rtrim($path, '/').'/', $matches);

        if (!$result)
            return false;


        ## merge match params definition:
        $params = array();
        foreach ($matches as $index => $val) {
            if (is_int($index) || $val === '')
                // parameter not given or just match index !!
                continue;

            $params[$index] = $val;
        }

        $routeMatch = clone $this;
        \Poirot\Router\mergeParamsIntoRouter($routeMatch, $params);

        ## aware of match uri path segment:
        if (!$this->isMatchWhole() && !$segment = $this->getSegment()) {
            // check for path segments to set into new route match object
            $origin = \Poirot\Std\Lexer\buildStringFromParsed($parts, $routeMatch->params());
            if ($origin == '/')
                ## with path equal to "/" explode is array with to empty item
                $end = array(' ');
            else
                $end = explode('/', $origin);
            
            $routeMatch->setSegment(array(0, count($end)));
        }
        
        return $routeMatch;
    }

    /**
     * Assemble the route to string with params
     *
     * - use default parameters self::params
     * - given parameters merged into defaults
     *
     * @param array|\Traversable $params Override defaults by merge
     *
     * @return UriInterface
     */
    function assemble($params = array())
    {
        ## merge params:
        $p = clone $this->params();
        // when parameters exactly given don't merge recursively and instead replace items
        if ($params) \Poirot\Router\mergeParams($p, $params, false);

        $p        = \Poirot\Std\cast($p)->toArray();
        $criteria = \Poirot\Std\Lexer\parseCriteria($this->getCriteria());
        $path     = \Poirot\Std\Lexer\buildStringFromParsed($criteria, $p);
        
        $uri = new Uri($path);
        return $uri;
    }


    // Options:

    /**
     * Set Criteria
     *
     * criteria can be one of the following:
     *
     * - '/en' or '/about'
     * - Regex Definition as params
     *   '/:locale{{\w{2}}}'
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
     * Set Path Offset To Match With Request Path Segment
     *
     * /var/www/html
     * [0]     => "/var/www/html"
     * [1]     => "var/www/html"
     * [0, 2]  => "/var"
     * [0, -1] => "/var/www"
     *
     * @param int|array|null $pathOffset [offset, length]
     *
     * @return $this
     */
    function setSegment($pathOffset)
    {
        if (is_int($pathOffset))
            $pathOffset = array($pathOffset, null);


        if (!is_array($pathOffset) && count($pathOffset) !==2)
            throw new \InvalidArgumentException(sprintf(
                'Segment is an array [$offset, $length]; given: (%s).'
                , \Poirot\Std\flatten($pathOffset)
            ));

        $this->pathOffset = $pathOffset;
        return $this;
    }

    /**
     * Get Path Offset
     *
     * - On match_whole option set to false
     *   if match with given criteria it must be
     *   return matched segment
     *
     * @return array|null
     */
    function getSegment()
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
    function setMatchWhole($exactMatch = true)
    {
        $this->exactMatch = (boolean) $exactMatch;
        return $this;
    }

    /**
     * Get Exact match
     *
     * !! if has segment option set the exact match result is false
     *
     * @return boolean
     */
    function isMatchWhole()
    {
        return empty($this->getSegment()) && $this->exactMatch;
    }
}
