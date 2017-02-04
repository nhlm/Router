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
     * Merge Params
     *
     * @param iData              $params
     * @param array|\Traversable $paramsToMerge
     * @param bool               $recursive
     *                           When parameters exactly given on assemble or something,
     *                           we don't need merge recursively and instead replace items
     */
    function mergeParams(iData $params, $paramsToMerge, $recursive = true)
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
            // TODO this way new parameter that given with assemble not override the old ones
            if ($recursive) {
                // Merge Recursive because may have chained Actions.
                $merged = new StdArray( $paramsToMerge );
                $t = \Poirot\Std\cast($params)->toArray();
                $paramsToMerge = $merged->withMergeRecursive($t);
            }

            $params->import($paramsToMerge);
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
