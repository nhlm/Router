<?php
namespace Poirot\Router\Http;

/**
 * [code]
    $Router = new RChainStack('main');
    $Router
        ->add(new RSegment('pages',
                [
                    'criteria'    => '/pages',
                    'exact_match' => false,
                ]
                , ['__action__' => 'check_user']
            )
        )->recent() ## recently added segment route "pages" as chain router
        ->link(new RSegment('static',
            [
                'criteria'    => 'static/mypage',
            ]
            , ['__action__' => 'display_static_mypage']
        ))
        ->add(new RSegment('page', ## add to "pages" recently router
            [
                'criteria'    => [':request_page' => ['request_page'=>'\w+'], ],
            ]
            , ['__action__' => 'display_page']
        ))
        ->parent() ## get back to parent "main" chain router stack
        ->add(new RSegment('auth',
            [
                'criteria'    => '/register',
            ]
        ));
    ;
 * [/code]
 */
class RChainStack extends HAbstractChainRouter
{

}
