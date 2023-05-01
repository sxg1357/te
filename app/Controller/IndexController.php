<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2023-05-01
 * Time: 21:40
 */

namespace App\Controller;

class IndexController extends BaseController {

    public function index() {
        $data = array_merge($this->request->_get, $this->request->_post);
        return json_encode($data);
    }
}
