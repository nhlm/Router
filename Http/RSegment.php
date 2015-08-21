<?php
namespace Poirot\Router\Http;

use Poirot\Core\Entity;
use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;
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

        protected function __match($request, $criteria, array $regexDef)
        {
            $uri  = $request->getUri();
            $path = $uri->getPath();

            $parts = $this->__parseRouteDefinition($criteria);

            $regex = $this->__buildRegex($parts, $regexDef);

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

            $pathOffset = null;

            if ($pathOffset !== null)
                $result = preg_match('(\G' . $regex . ')', $path->toString(), $matches, null, $pathOffset);
            else
                $result = preg_match('(^' . $regex . '$)', $path->toString(), $matches);

            if (!$result)
                return false;

            $matchedLength = strlen($matches[0]);

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
        // TODO: Implement assemble() method.
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
