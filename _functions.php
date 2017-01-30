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
            $paramsToMerge = \Poirot\Std\cast($paramsToMerge)->toArray();

        if (!is_array($paramsToMerge))
            throw new \InvalidArgumentException(sprintf(
                'Parameters must be an array or instance of Traversable; given: (%s).'
                , \Poirot\Std\flatten($paramsToMerge)
            ));

        if (!empty($paramsToMerge)) {
            // note: we use route params merge to execute chained action on route match
            //       and for this index(priority) of actions in list params in mandatory
            //       the first route param first then children param after that
            $merged = new StdArray( $paramsToMerge );
            // Merge Recursive because may have chained Actions.
            $params->import($merged->withMergeRecursive( \Poirot\Std\cast($params)->toArray() ));
        }
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
