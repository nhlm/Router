<?php
namespace Poirot\Router\Http;

use Poirot\Core\Entity;
use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;
use Poirot\PathUri\SeqPathJoinUri;
use Poirot\Router\Interfaces\Http\iHRouter;

class RSegment extends HAbstractRouter
{
    /**
     * Map from regex groups to parameter names.
     *
     * @var array
     */
    protected $_paramMap = [];

    /**
     * Translation keys used in the regex.
     *
     * @var array
     */
    protected $_translationKeys = [];

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
        $routerMatch = false;

        $criteria = $this->options()->getCriteria();
        if (is_array($criteria)) {
            foreach($criteria as $ci => $nllRegex) {
                $regexDef = [];

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

                $routerMatch = $this->__match($request, $criteria, $regexDef);
                if ($routerMatch)
                    # return match
                    break;
            }
        } else {
            $routerMatch = $this->__match($request, $criteria, []);
        }

        return $routerMatch;
    }

        protected function __match(iHttpRequest $request, $criteria, array $regexDef)
        {
            $uri  = $request->getUri();
            $path = $uri->getPath();

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
            $parts = $this->__parseRouteDefinition($criteria);
            $regex = $this->__buildRegex($parts, $regexDef);

            $pathOffset = $this->options()->getPathOffset();
            if(!$pathOffset && $request->meta()->__isset('__router_segment__')) {
                $pathOffset = $request->meta()->__router_segment__;
                $pathOffset = [end($pathOffset), null];       ### offset from last match to end
            }

            if ($pathOffset !== null) {
                ## extract path offset to match
                $path   = call_user_func_array([$path, 'split'], $pathOffset);
                $result = preg_match('(\G' . $regex . ')', $path->toString(), $matches);
            }
            else {
                $regex = ($this->options()->getExactMatch())
                    ? "(^{$regex}$)" ## exact match
                    : "(^{$regex})"; ## only start with criteria "/pages[/other/paths]"

                $result = preg_match($regex, $path->toString(), $matches);

                ## calculate match path offset

                (!$result) ?:
                    $pathOffset = [
                        $path->getDepth() - $path->mask(new SeqPathJoinUri($matches[0]))->getDepth()
                        , null
                    ];
            }

            if (!$result)
                return false;

            ### inject offset as metadata to get back on linked routers
            if ($pathOffset) {
                $this->options()->setPathOffset($pathOffset); ### using on assemble things and ...
                $request->meta()->__router_segment__ = $pathOffset;
            }

            $params = [];
            foreach ($this->_paramMap as $index => $name) {
                if (isset($matches[$index]) && $matches[$index] !== '')
                    $params[$name] = $this->_decode($matches[$index]);
            }

            $routerMatch = clone $this;
            $routerMatch->params()->merge(new Entity($params));

            return $routerMatch;
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
        $criteriaOpt = $this->options()->getCriteria();

        // TODO fix gather criteria when multiple match is sent
        //      ['criteria', ':subDomain.site.com' => ['subDomain' => 'fw\d{2}'] ...]
        $criteria = (is_array($criteriaOpt))
            ? key($criteriaOpt)
            : $criteriaOpt
        ;

        $parts = $this->__parseRouteDefinition($criteria);
        $path  = $this->__buildPath(
            $parts
            , array_merge($this->params()->toArray(), $params)
            , false
        );

        $httpUri = new HttpUri(['path' => $path]);
        return $httpUri;
    }

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
    protected function __buildPath(array $parts, array $mergedParams, $isOptional)
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
                    $optionalPart = $this->__buildPath($part[1], $mergedParams, true);

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

    /**
     * Parse a route definition.
     *
     * @param  string $def
     * @return array
     * @throws \RuntimeException
     */
    protected function __parseRouteDefinition($def)
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
    protected function __buildRegex(array $parts, array $constraints, &$groupIndex = 1)
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
                    $regex .= '(?:' . $this->__buildRegex($part[1], $constraints, $groupIndex) . ')?';
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

    /**
     * !! just for IDE auto completion integration
     *
     * @return RSegmentOpts
     */
    function options()
    {
        return parent::options();
    }

    /**
     * @inheritdoc
     * @return RSegmentOpts
     */
    static function optionsIns()
    {
        return new RSegmentOpts;
    }
}
