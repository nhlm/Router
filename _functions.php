<?php
namespace Poirot\Router 
{
    use Poirot\Router\Interfaces\iRoute;
    use Poirot\Std\Interfaces\Struct\iData;
    use Poirot\Std\Type\StdArray;

    
    /**
     * Merge given params with route defaults
     * @param iRoute             $route
     * @param array|\Traversable $params
     */
    function mergeParamsIntoRouter(iRoute $route, $params)
    {
        mergeParams($route->params(), $params);
    }

    /**
     * Merge Params Recursively
     * @param iData              $params
     * @param array|\Traversable $paramsToMerge
     */
    function mergeParams(iData $params, $paramsToMerge)
    {
        if ($paramsToMerge instanceof \Traversable)
            $paramsToMerge = \Poirot\Std\cast($this->params())->toArray();

        if (!is_array($paramsToMerge))
            throw new \InvalidArgumentException(sprintf(
                'Parameters must be an array or instance of Traversable; given: (%s).'
                , \Poirot\Std\flatten($paramsToMerge)
            ));

        $merged = new StdArray(\Poirot\Std\cast($params)->toArray());
        $merged->mergeRecursive($paramsToMerge);
        $params->import($merged);
    }
    
    function encodeUrl($value)
    {
        return rawurlencode($value);
    }

    function decodeUrl($value)
    {
        return rawurldecode($value);
    }
}
