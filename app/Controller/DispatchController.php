<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023-05-01
 * Time: 21:42
 */

namespace App\Controller;

use Socket\Ms\Request;
use Socket\Ms\Response;

class DispatchController {

    public function callAction($routes, Request $request, Response $response) {
        $uri = $request->_request['uri'];
        if (isset($routes[$uri])) {
            list($class, $method) = explode('@', $routes[$uri], 2);
            try {
                $controller = new $class($request, $response);
                if (method_exists($controller, $method)) {
                    $result = $controller->{$method}();
                } else {
                    $result = "method not found";
                }
            } catch (\Exception $e) {
                $result = $e->getMessage();
            }
        } else {
            $result = "route not found";
        }
        $response->setHeader("Content-Type", "application/json");
        $response->write($result);
    }
}