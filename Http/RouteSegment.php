<?php
namespace Poirot\Router\Http;

/*
 * Match Uri segment against criteria
 *
 * [code]
 * new RSegment('page',
 *       [
 *         ## options
 *           ### request_page defined as parameter and will merged with def. params on success match
 *           'criteria'    => [':request_page' => ['request_page'=>'\w+'], ],
 *           'criteria'    => '/path/uri/to/match',
 *           ### match exact or just current part of criteria with given request uri
 *           'exact_match' => true,
 *       ]
 *         ## params
 *       , ['__action__' => 'display_page']
 *   )
 * [/code]
 *
 * TODO refactor codes
 */
use Poirot\Psr7\Uri;
use Poirot\Router\Interfaces\iRoute;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class RouteSegment 
    extends aRoute
{
    /**
     * Map from regex groups to parameter names.
     * @var array
     */
    protected $_paramMap = array();

    /**
     * Translation keys used in the regex.
     * @var array
     */
    protected $_translationKeys = array();
    
    // options

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
     * Match with Request
     *
     * TODO we inject meta data to request object
     *      this request object can be used again
     *      for other route matches.
     *      with current algorithm we have conflict
     *      on other calls request match
     *
     * - merge with current params
     *
     * - manipulate params on match
     *   exp. when match host it contain host param
     *   with matched value
     *
     * @param RequestInterface $request
     *
     * @return iRoute|false
     */
    function match(RequestInterface $request)
    {
        $routerMatch = false;

        $criteria = $this->getCriteria();

        if (is_array($criteria)) {
            foreach($criteria as $ci => $nllRegex) {
                $regexDef = array();

                if (is_string($ci)) {
                    ## [':criteria' => ['criteria'=>'...']]
                    $criteria = $ci;
                    $regexDef = $nllRegex;
                    if (!is_array($regexDef))
                        throw new \InvalidArgumentException(sprintf(
                            'Invalid Criteria format provided. it must match '
                            .'"[\':criteria\' => [\'criteria\'=>\'...\']]" '
                            .'but "%s" given.'
                            , is_object($regexDef) ? get_class($regexDef) : gettype($regexDef)
                        ));
                } else
                    ## ['hostname', ...]
                    $criteria = $nllRegex;

                $routerMatch = $this->_match($request, $criteria, $regexDef);
                if ($routerMatch)
                    # return match
                    break;
            }
        } else {
            $routerMatch = $this->_match($request, $criteria, array());
        }

        return $routerMatch;
    }

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return UriInterface
     */
    function assemble(array $params = array())
    {
        $criteriaOpt = $this->getCriteria();

        // TODO fix gather criteria when multiple match is sent
        //      ['criteria', ':subDomain.site.com' => ['subDomain' => 'fw\d{2}'] ...]
        $criteria = (is_array($criteriaOpt))
            ? key($criteriaOpt)
            : $criteriaOpt
        ;

        $parts = $this->_parseRouteDefinition($criteria);
        $path  = $this->_buildPath(
            $parts
            , array_merge(\Poirot\Std\cast($this->params())->toArray(), $params)
            , false
        );

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
            $pathOffset = array($pathOffset, null);

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
    
    
    // ..

    /**
     * Build a path.
     *
     * @param  array   $parts
     * @param  array   $mergedParams
     * @param  bool    $isOptional
     * @return string
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function _buildPath(array $parts, array $mergedParams, $isOptional)
    {
        $path      = '';
        $skip      = true;
        $skippable = false;

        foreach ($parts as $part) {
            switch ($part[0]) {
                case 'literal':
                    $path .= $part[1];
                    break;

                case 'parameter':
                    $skippable = true;

                    if (!isset($mergedParams[$part[1]])) {
                        if (!$isOptional)
                            throw new \InvalidArgumentException(sprintf('Missing parameter "%s"', $part[1]));
                        return '';
                    }
                    elseif (!$isOptional
                        || !$this->params()->has($part[1])
                        || $this->params()->get($part[1]) !== $mergedParams[$part[1]]
                    )
                        $skip = false;

                    $path .= $this->_encode($mergedParams[$part[1]]);

//                    $this->assembledParams[] = $part[1];
                    break;

                case 'optional':
                    $skippable    = true;
                    $optionalPart = $this->_buildPath($part[1], $mergedParams, true);

                    if ($optionalPart !== '') {
                        $path .= $optionalPart;
                        $skip  = false;
                    }
                    break;

                case 'translated-literal':
                    $path .= $translator->translate($part[1], $textDomain, $locale);
                    break;
            }
        }

        if ($isOptional && $skippable && $skip)
            return '';

        return $path;
    }


    protected function _match(RequestInterface $request, $criteria, array $regexDef)
    {
        $path = $request->getUri()->getPath();

        /*if ($this->_translationKeys) {
            if (!isset($options['translator']) || !$options['translator'] instanceof Translator) {
                throw new \RuntimeException('No translator provided');
            }

            $translator = $options['translator'];
            $textDomain = (isset($options['text_domain']) ? $options['text_domain'] : 'default');
            $locale     = (isset($options['locale']) ? $options['locale'] : null);

            foreach ($this->_translationKeys as $key) {
                $regex = str_replace('#' . $key . '#', $translator->translate($key, $textDomain, $locale), $regex);
            }
        }*/

        # match criteria:
        $parts = $this->_parseRouteDefinition($criteria);
        $regex = $this->_buildRegex($parts, $regexDef);

        ## hash meta for router segment, unique for each file call
        /*$backTrace = debug_backtrace(null, 1);
        $hashMeta  = end($backTrace)['file'];*/
        $hashMeta  = 'ds';

        $pathOffset    = $this->getPathOffset();
        $routerSegment = $request->meta()->__router_segment__;
        if ($routerSegment) {
            $routerSegment = (isset($routerSegment[$hashMeta]))
                ? $routerSegment = $routerSegment[$hashMeta]
                : null;
        }

        if(!$pathOffset && $routerSegment) {
            $pathOffset = $routerSegment;
            $pathOffset = array(end($pathOffset), null); ### offset from last match to end(null), used on split
        }

        if ($pathOffset !== null)
            ## extract path offset to match
            $path   = call_user_func_array(array($path, 'split'), $pathOffset);

        $regex = ($this->getExactMatch())
            ? "(^{$regex}$)" ## exact match
            : "(^{$regex})"; ## only start with criteria "/pages[/other/paths]"

        $result = preg_match($regex, $path->toString(), $matches);

        if ($result) {
            ## calculate matched path offset
            $curMatchDepth = (new SeqPathJoinUri($matches[0]))->getDepth();

            if (!$pathOffset) {
                $start = null;
                $end   = $curMatchDepth;
            } else {
                $start = current($pathOffset) + $curMatchDepth;
                $end   = $start + $curMatchDepth;
            }

            $pathOffset = array($start, $end);
        }

        if (!$result)
            return false;

        ### inject offset as metadata to get back on linked routers
        if ($pathOffset) {
//                $this->options()->setPathOffset($pathOffset); ### using on assemble things and ...
            $rSegement = &$request->meta()->__router_segment__;
            if (!is_array($rSegement))
                $rSegement = array();
            $rSegement[$hashMeta] = $pathOffset;
        }

        $params = array();
        foreach ($this->_paramMap as $index => $name) {
            if (isset($matches[$index]) && $matches[$index] !== '')
                $params[$name] = $this->_decode($matches[$index]);
        }

        $routerMatch = clone $this;
        $routerMatch->params()->import($params);

        return $routerMatch;
    }

    /**
     * Parse a route definition.
     *
     * @param  string $def
     * @return array
     * @throws \RuntimeException
     */
    protected function _parseRouteDefinition($def)
    {
        $currentPos = 0;
        $length     = strlen($def);
        $parts      = array();
        $levelParts = array(&$parts);
        $level      = 0;

        while ($currentPos < $length) {
            preg_match('(\G(?P<literal>[^:{\[\]]*)(?P<token>[:{\[\]]|$))', $def, $matches, 0, $currentPos);

            $currentPos += strlen($matches[0]);

            if (!empty($matches['literal'])) {
                $levelParts[$level][] = array('literal', $matches['literal']);
            }

            if ($matches['token'] === ':') {
                if (!preg_match('(\G(?P<name>[^:/{\[\]]+)(?:{(?P<delimiters>[^}]+)})?:?)', $def, $matches, 0, $currentPos)) {
                    throw new \RuntimeException('Found empty parameter name');
                }

                $levelParts[$level][] = array('parameter', $matches['name'], isset($matches['delimiters']) ? $matches['delimiters'] : null);

                $currentPos += strlen($matches[0]);
            } elseif ($matches['token'] === '{') {
                if (!preg_match('(\G(?P<literal>[^}]+)\})', $def, $matches, 0, $currentPos)) {
                    throw new \RuntimeException('Translated literal missing closing bracket');
                }

                $currentPos += strlen($matches[0]);

                $levelParts[$level][] = array('translated-literal', $matches['literal']);
            } elseif ($matches['token'] === '[') {
                $levelParts[$level][] = array('optional', array());
                $levelParts[$level + 1] = &$levelParts[$level][count($levelParts[$level]) - 1][1];

                $level++;
            } elseif ($matches['token'] === ']') {
                unset($levelParts[$level]);
                $level--;

                if ($level < 0) {
                    throw new \RuntimeException('Found closing bracket without matching opening bracket');
                }
            } else {
                break;
            }
        }

        if ($level > 0) {
            throw new \RuntimeException('Found unbalanced brackets');
        }

        return $parts;
    }

    /**
     * Build the matching regex from parsed parts.
     *
     * @param  array   $parts
     * @param  array   $constraints
     * @param  int $groupIndex
     * @return string
     * @throws \RuntimeException
     */
    protected function _buildRegex(array $parts, array $constraints, &$groupIndex = 1)
    {
        $regex = '';

        foreach ($parts as $part) {
            switch ($part[0]) {
                case 'literal':
                    $regex .= preg_quote($part[1]);
                    break;

                case 'parameter':
                    $groupName = '?P<param' . $groupIndex . '>';

                    if (isset($constraints[$part[1]])) {
                        $regex .= '(' . $groupName . $constraints[$part[1]] . ')';
                    } elseif ($part[2] === null) {
                        $regex .= '(' . $groupName . '[^/]+)';
                    } else {
                        $regex .= '(' . $groupName . '[^' . $part[2] . ']+)';
                    }

                    $this->_paramMap['param' . $groupIndex++] = $part[1];
                    break;

                case 'optional':
                    $regex .= '(?:' . $this->_buildRegex($part[1], $constraints, $groupIndex) . ')?';
                    break;

                case 'translated-literal':
                    $regex .= '#' . $part[1] . '#';
                    $this->_translationKeys[] = $part[1];
                    break;
            }
        }

        return $regex;
    }

    /**
     * Encode a path segment.
     *
     * @param  string $value
     * @return string
     */
    protected function _encode($value)
    {
        return rawurlencode($value);
    }

    /**
     * Decode a path segment.
     *
     * @param  string $value
     * @return string
     */
    protected function _decode($value)
    {
        return rawurldecode($value);
    }
}
