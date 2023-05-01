<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023-05-01
 * Time: 21:43
 */

namespace App\Controller;

use Socket\Ms\Request;
use Socket\Ms\Response;

class BaseController {

    public $request;
    public $response;

    public function __construct(Request $request, Response $response) {
        $this->request = $request;
        $this->response = $response;
    }

}