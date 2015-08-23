# Router
Amazing Fast &amp; Flexible Php Router

## Chain Stack Router

```php
$request = new HttpRequest(new PhpServerOptsAware);

$Router = RChainStack::factory([
    'name'   => 'main',
    'routes' => [
        'pages' => [ ## main/pages
            'route'   => 'segment',
            'options' => [
                'criteria'    => '/pages',
                'exact_match' => false,
            ],
            'params'  => ['__action__' => 'check_user'],
            ### add child routes
            'routes'  => [
                'static' => [
                    'route'   => 'segment',
                    'options' => [
                        'criteria' => 'static/mypage',
                    ],
                    'params'  => ['__action__' => 'display_static_mypage'],
                ],
                'page'   => [
                    'route'   => 'segment',
                    'options' => [
                        'criteria' => [':request_page' => ['request_page'=>'\w+'], ],
                    ],
                    'params'  => ['__action__' => 'display_page']
                ],
            ], ### end child routes
        ], ## end pages route
        'auth' => [
            'route'   => 'segment',
            'options' => ['criteria'    => '/register'],
            'params'  => ['__action__' => 'this.is.action']
        ],
    ], # end main child routes
]);

$match = $Router->match($request);
```

## TODO

Documentation
