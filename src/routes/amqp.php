<?php

use Sofnet\AmqpConnector\Facades\Route;
use Sofnet\AmqpConnector\Request;

Route::async('test', function (Request $request) {
    $body = $request->getBody();
    return "Hello, World!";
});
