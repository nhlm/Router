<?php
namespace Poirot\Router\Http;

use Poirot\Core\AbstractOptions;
use Poirot\Core\Entity;
use Poirot\Http\Interfaces\Message\iHttpRequest;
use Poirot\PathUri\HttpUri;
use Poirot\Router\Interfaces\Http\iHRouter;

/*
 *
 * TODO refactor codes
 */

class RHostname extends HAbstractRouter
{
    /**
     * Map from regex groups to parameter names.
     *
     * @var array
     */
    protected $_paramMap = array();

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
        $criteria = $this->options()->getCriteria();

        $routerMatch = false;
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
            ## host can include user/pass, port
            $host = $request->getHost();

            $pHost = parse_url($host);
            $host  = (isset($pHost['host'])) ? $pHost['host']: $host;

            $parts      = $this->__parseRouteDefinition($criteria);
            $buildRegex = $this->__buildRegex($parts, $regexDef);
            $result     = preg_match('(^' . $buildRegex . '$)', $host, $matches);

            if (!$result)
                ## route not matched
                return false;

            $params = [];
            foreach ($this->_paramMap as $index => $name) {
                if (isset($matches[$index]) && $matches[$index] !== '')
                    $params[$name] = $matches[$index];
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
        $host  = $this->__buildHost(
            $parts
            , array_merge($this->params()->toArray(), $params)
            , false
        );

        return (new HttpUri())->setHost($host);
    }

    /**
     * Build host.
     *
     * @param  array   $parts
     * @param  array   $mergedParams
     * @param  bool    $isOptional
     * @return string
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function __buildHost(array $parts, array $mergedParams, $isOptional)
    {
        $host      = '';
        $skip      = true;
        $skippable = false;

        foreach ($parts as $part) {
            switch ($part[0]) {
                case 'literal':
                    $host .= $part[1];
                    break;

                case 'parameter':
                    $skippable = true;

                    if (!isset($mergedParams[$part[1]])) {
                        if (!$isOptional)
                            throw new \InvalidArgumentException(sprintf(
                                'Missing parameter "%s"'
                                , $part[1]
                            ));

                        return '';
                    } elseif (!$isOptional
                        || !$this->params()->has($part[1])
                        || $this->params()->get($part[1]) !== $mergedParams[$part[1]]
                    )
                        $skip = false;

                    $host .= $mergedParams[$part[1]];

//                    $this->assembledParams[] = $part[1];
                    break;

                case 'optional':
                    $skippable    = true;
                    $optionalPart = $this->__buildHost($part[1], $mergedParams, true);

                    if ($optionalPart !== '') {
                        $host .= $optionalPart;
                        $skip  = false;
                    }
                    break;
            }
        }

        if ($isOptional && $skippable && $skip)
            return '';

        return $host;
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
            preg_match('(\G(?P<literal>[a-z0-9-.]*)(?P<token>[:{\[\]]|$))', $def, $matches, 0, $currentPos);

            $currentPos += strlen($matches[0]);

            if (!empty($matches['literal'])) {
                $levelParts[$level][] = array('literal', $matches['literal']);
            }

            if ($matches['token'] === ':') {
                if (!preg_match('(\G(?P<name>[^:.{\[\]]+)(?:{(?P<delimiters>[^}]+)})?:?)', $def, $matches, 0, $currentPos)) {
                    throw new \RuntimeException('Found empty parameter name');
                }

                $levelParts[$level][] = array('parameter', $matches['name'], isset($matches['delimiters']) ? $matches['delimiters'] : null);

                $currentPos += strlen($matches[0]);
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
                        $regex .= '(' . $groupName . '[^.]+)';
                    } else {
                        $regex .= '(' . $groupName . '[^' . $part[2] . ']+)';
                    }

                    $this->_paramMap['param' . $groupIndex++] = $part[1];
                    break;

                case 'optional':
                    $regex .= '(?:' . $this->__buildRegex($part[1], $constraints, $groupIndex) . ')?';
                    break;
            }
        }

        return $regex;
    }

    /**
     * !! just for IDE auto completion integration
     *
     * @return RHostnameOpts
     */
    function options()
    {
        return parent::options();
    }

    /**
     * @inheritdoc
     * @return RHostnameOpts
     */
    static function optionsIns()
    {
        return new RHostnameOpts;
    }
}
