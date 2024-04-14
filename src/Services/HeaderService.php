<?php declare(strict_types=1); 

namespace App\Services;

class HeaderService {
    public function send($header, $replace = true, $http_response_code = null) {
        $http_response_code === null 
            ? header($header, $replace) 
            : header($header, $replace, $http_response_code);
    }
}
