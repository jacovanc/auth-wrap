<?php declare(strict_types=1);

namespace App;

class Helper {
    public static function addSchema(string $url): string {
       // If localhost, use http otherwise https
        if (strpos($url, 'localhost') !== false) {
            $url = "http://" . $url;
        } else {
            $url = "https://" . $url; // The redirect from Nginx will never contain a protocol, and we want to force HTTPS
        }

        return $url;
    }
}

