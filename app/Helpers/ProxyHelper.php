<?php

namespace App\Helpers;

class ProxyHelper
{

    public static function fetchAndCheckProxy($apiUrl = null)
    {
        if ($apiUrl == null) $apiUrl = "https://tiktok.lovanthao.com/api/get_proxy";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            curl_close($ch);
            return [ 'status' => 'error', 'message' => curl_error($ch) ];
        }
        curl_close($ch);

        if (is_string($response)) {
            $proxyParts = explode(':', trim($response));
            if (count($proxyParts) === 4) {
                return [
                    'status' => 'success',
                    'proxy' => [
                        'ip' => $proxyParts[0],
                        'port' => $proxyParts[1],
                        'user' => $proxyParts[2],
                        'pass' => $proxyParts[3]
                    ]
                ];
            }
        }
        return [ 'status' => 'error', 'message' => 'Định dạng proxy không hợp lệ' ];
    }
}
