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
     * @return false|iRoute
     * @throws \Exception
     */
    function match(RequestInterface $request)
    {
        ## host may have port
        /** @var RequestInterface $request */
        $host = $request->getHeaderLine('Host');
        if (!$host)
            throw new \Exception('Host not recognized in Request.');


        $parts      = \Poirot\Std\Lexer\parseDefinition($this->getCriteria());
        $regexMatch = \Poirot\Std\Lexer\buildRegexFromParsed($parts);
        $result     = preg_match('(^' . $regexMatch . '$)', $host, $matches);

        if (!$result)
            ## route not matched
            return false;

        
        $params = array();
        foreach ($matches as $index => $val) {
            if (is_int($index)) continue;

            $params[$index] = $val;
        }

        $routerMatch = clone $this;
        $routerMatch->params()->import($params);
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
}
