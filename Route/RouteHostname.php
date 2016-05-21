<?php
namespace Poirot\Router\Route;

/*
 *
 * TODO refactor codes
 */

use Poirot\Psr7\Uri;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

use Poirot\Router\aRoute;
use Poirot\Router\Interfaces\iRoute;

/**
 * - Host name may have :port number
 *
 */

class RouteHostname 
    extends aRoute
{
    /** @var string|array */
    protected $criteria;
    
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
        $routerMatch = $this->_match($request, $this->getCriteria());
        return $routerMatch;
    }

    /**
     * Assemble the route to string with params
     *
     * @param array $params
     *
     * @return UriInterface
     */
    function assemble($params = array())
    {
        $criteriaOpt = $this->getCriteria();

        // TODO fix gather criteria when multiple match is sent
        //      ['criteria', ':subDomain.site.com' => ['subDomain' => 'fw\d{2}'] ...]
        $criteria = (is_array($criteriaOpt))
            ? key($criteriaOpt)
            : $criteriaOpt
        ;

        $parts = $this->_parseStringDefinition($criteria);
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
        $this->criteria = $hostCriteria;
        return $this;
    }

    /**
     * Get Criteria
     *
     * @return array|string
     */
    function getCriteria()
    {
        return $this->criteria;
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
    

    protected function _match(RequestInterface $request, $criteria)
    {
        ## host may have port
        /** @var RequestInterface $request */
        $host = $request->getHeaderLine('Host');
        if (!$host)
            throw new \Exception('Host not recognized in Request.');


        $parts      = $this->_parseStringDefinition($criteria);

        kd($parts);

        $buildRegex = $this->_buildRegex($parts, array());
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
     * Parse a string variable/literal definition.
     * 
     * @param  string $interchange
     * @return array
     * @throws \RuntimeException
     */
    protected function _parseStringDefinition($interchange)
    {
        $currentPos = 0;
        $length     = strlen($interchange);

        $parts      = array();
        $levelParts = array(&$parts);
        $level      = 0;

        while ($currentPos < $length)
        {
            ## the tokens are .:{[]
            preg_match('(\G(?P<_literal_>[A-Za-z0-9-]*)(?P<_token_>[.:{\[\]]|$))'
                , $interchange
                , $matches
                , 0
                , $currentPos
            );
            $currentPos += strlen($matches[0]);

            if (!empty($matches['_literal_']))
                $levelParts[$level][] = array('_literal_' => $matches['_literal_']);

            # Deal With Token:
            if (!isset($matches['_token_']))
                continue;

            $Token = $matches['_token_'];
            if ($Token === ':') {
                $pmatch = preg_match('(\G(?P<_name_>[^:.{\[\]]+)(?:{(?P<_delimiter_>[^}]+)})?:?)'
                    , $interchange
                    , $matches
                    , 0
                    , $currentPos
                );
                if (!$pmatch)
                    throw new \RuntimeException('Found empty parameter name');

                $parameter = $matches['_name_'];
                $val       = array('_parameter_' => $parameter);
                if (isset($matches['_delimiter_']))
                    $val[$parameter] = $matches['_delimiter_'];

                $levelParts[$level][] = $val;
                $currentPos += strlen($matches[0]);
            }

            if ($Token === '[') {
                $va = array();
                $levelParts[$level][]   = array('_optional_' => &$va);
                $levelParts[$level + 1] = &$va;

                $level++;
            }
            
            if ($Token === ']') {
                unset($levelParts[$level]);
                $level--;

                if ($level < 0)
                    throw new \RuntimeException('Found closing bracket without matching opening bracket');
            }
            
        } // end while

        if ($level > 0)
            throw new \RuntimeException('Found unbalanced brackets');

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
