<?php
namespace FastApiPHP;


class Controller
{
    public $requireAuth = true;
    public $APP;


    public function __construct($app, $request, $response, $args)
    {
        $this->APP = $app;
    }


}
