<?php
namespace Poirot\Router\Route;

use Poirot\Psr7\Uri;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

use Poirot\Router\aRoute;
use Poirot\Router\Interfaces\iRoute;

/**
 * - Host name may have :port number
 *   TODO append host to uri routes when assemble
 *        - have optional setter to prepend host to uri
 *        - use matched host in case when criteria is regex and have not any builder options:
 *          storage.~.+~
 */
class RouteHostname 
    extends aRoute
{
    
    // Options:
    
    /** @var string|array */
    protected $criteria;
    
    
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
     * @return RouteHostname|iRoute|false usually clone/copy of matched route
     * @throws \Exception
     */
    function match(RequestInterface $request)
    {
        ## host may have port
        /** @var RequestInterface $request */
        $host = $request->getHeaderLine('Host');
        if (! $host )
            throw new \Exception('Host not recognized in Request.');

        $criteria   = $this->getCriteria();
        $parts      = \Poirot\Std\Lexer\parseCriteria($criteria, '.');
        $regexMatch = \Poirot\Std\Lexer\buildRegexFromParsed($parts);
        $result     = preg_match('(^' . $regexMatch . '$)', $host, $matches);


        if (! $result )
            ## route not matched
            return false;

        ## merge match params definition:
        $params = array();
        foreach ($matches as $index => $val) {
            if (is_int($index)) continue;

            $params[$index] = $val;
        }

        $routerMatch = clone $this;
        if (! empty($params))
            \Poirot\Router\mergeParamsIntoRouter($routerMatch, $params);

        return $routerMatch;
    }

    /**
     * Assemble the route to string with params
     *
     * @param array|\Traversable $params Override defaults by merge
     *
     * @return UriInterface
     */
    function assemble($params = null)
    {
        return new Uri();


        ## merge params:
        $p = clone $this->params();
        if ($params) \Poirot\Router\mergeParams($p, $params);
        
        $host = \Poirot\Std\Lexer\buildStringFromParsed(
            \Poirot\Std\Lexer\parseCriteria($this->getCriteria())
            , \Poirot\Std\cast($p)->toArray()
        );

        $uri  = new Uri();
        $purl = parse_url($host);
        if (isset($purl['port'])) {
            $uri = $uri->withPort($purl['port']);
            $host = str_replace(':'.$purl['port'], '', $host);
        }

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
}
