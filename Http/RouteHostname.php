<?php
namespace Poirot\Router\Http;

/*
 *
 * TODO refactor codes
 */

use Poirot\Psr7\Uri;
use Poirot\Router\Interfaces\iRoute;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class RouteHostname 
    extends aRoute
{
    /** @var string|array */
    protected $hostCriteria;
    
    /**
     * Map from regex groups to parameter names.
     * @var array
     */
    protected $_paramMap = array();

    /**
     * Match with Request
     *
     * - on match extract request params and merge
     *   into default params
     *
     * !! don`t change request object attributes
     *
     * @param RequestInterface $request
     *
     * @return iRoute|false
     */
    function match(RequestInterface $request)
    {
        $criteria = $this->getCriteria();

        $routerMatch = false;
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
        $host  = $this->_buildHost(
            $parts
            , array_merge(\Poirot\Std\cast($this->params())->toArray(), $params)
            , false
        );

        $uri = new Uri();
        return $uri->withHost($host);
    }
    
    
    // Options: 

    /**
     * Set Criteria
     *
     * criteria can be one of the following:
     *
     * - 'mysite.com' or 'localhost' or 'sb.site.tld'
     * - Regex Definition as params
     *   [':subDomain.site.com' => ['subDomain' => 'fw\d{2}'] ...]
     *
     * @param array|string $hostCriteria
     *
     * @return $this
     */
    function setCriteria($hostCriteria)
    {
        $this->hostCriteria = $hostCriteria;
        return $this;
    }

    /**
     * Get Criteria
     *
     * @return array|string
     */
    function getCriteria()
    {
        return $this->hostCriteria;
    }
    
    
    // ..

    /**
     * Assemble Build host.
     *
     * @param  array   $parts
     * @param  array   $mergedParams
     * @param  bool    $isOptional
     * @return string
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     */
    protected function _buildHost(array $parts, array $mergedParams, $isOptional)
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
                    $optionalPart = $this->_buildHost($part[1], $mergedParams, true);

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
    

    protected function _match($request, $criteria, array $regexDef)
    {
        ## host can include user/pass, port
        /** @var RequestInterface $request */
        $host = $request->getUri()->getHost();

        $pHost = parse_url($host);
        $host  = (isset($pHost['host'])) ? $pHost['host']: $host;

        $parts      = $this->_parseRouteDefinition($criteria);
        $buildRegex = $this->_buildRegex($parts, $regexDef);
        $result     = preg_match('(^' . $buildRegex . '$)', $host, $matches);

        if (!$result)
            ## route not matched
            return false;

        $params = array();
        foreach ($this->_paramMap as $index => $name) {
            if (isset($matches[$index]) && $matches[$index] !== '')
                $params[$name] = $matches[$index];
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
                        $regex .= '(' . $groupName . '[^.]+)';
                    } else {
                        $regex .= '(' . $groupName . '[^' . $part[2] . ']+)';
                    }

                    $this->_paramMap['param' . $groupIndex++] = $part[1];
                    break;

                case 'optional':
                    $regex .= '(?:' . $this->_buildRegex($part[1], $constraints, $groupIndex) . ')?';
                    break;
            }
        }

        return $regex;
    }
}
