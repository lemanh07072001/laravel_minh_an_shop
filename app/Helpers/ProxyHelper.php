<?php

namespace App\Helpers;

class ProxyHelper
{
    public static function fetchMultipleProxies($limit = 10)
    {
        $proxies = [];
        $ips = [];
        $maxAttempts = $limit * 5;
        $attempt = 0;

        while (count($ips) < $limit && $attempt < $maxAttempts) {
            $result = self::fetchAndCheckProxy();

            if ($result['status'] === 'success') {
                $ips[] = $result['proxy']['ip'];
                $proxies[] = $result['proxy'];
            }
            $attempt++;
            usleep(300000); // Nghỉ 0.3s tránh spam server
        }

        // Check country song song
        $countries = self::getProxyCountries($ips);

        $finalProxies = [];
        foreach ($countries as $key => $info) {
            if ($info['status'] === 'success' && $info['countryCode'] === 'IT') {
                $finalProxies[] = $proxies[$key];
            }
        }

        if (count($finalProxies) === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy proxy Trung Quốc nào.'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Danh sách proxy IT',
            'data' => $finalProxies
        ]);
    }

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

    public static function getProxyCountries(array $ips)
    {
        $multiHandle = curl_multi_init();
        $curlHandles = [];
        $results = [];

        foreach ($ips as $i => $ip) {
            $url = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,message";
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_multi_add_handle($multiHandle, $ch);
            $curlHandles[$i] = $ch;
        }

        $running = null;
        do {
            curl_multi_exec($multiHandle, $running);
            curl_multi_select($multiHandle);
        } while ($running > 0);

        foreach ($curlHandles as $i => $ch) {
            $response = curl_multi_getcontent($ch);
            $data = json_decode($response, true);
            $results[$i] = $data;
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);
        return $results;
    }

    public static function getProxyCountry($ip)
    {
        $apiUrl = "http://ip-api.com/json/{$ip}?fields=status,country,countryCode,message";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'status' => 'error',
                'message' => $error
            ];
        }

        $data = json_decode($response, true);
        if ($data['status'] !== 'success') {
            return [
                'status' => 'error',
                'message' => $data['message'] ?? 'Lỗi lấy country'
            ];
        }

        return [
            'status' => 'success',
            'country' => $data['country'],
            'countryCode' => $data['countryCode']
        ];
    }

    public static function getPing($ip)
    {
        $output = [];
        $result = null;

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("ping -n 1 -w 1000 $ip", $output, $result);
        } else {
            exec("ping -c 1 -W 1 $ip", $output, $result);
        }

        foreach ($output as $line) {
            if (preg_match('/time=([\d\.]+)\s*ms/', $line, $matches)) {
                return (float) $matches[1];
            }
        }

        return false;
    }

    public static function testProxyCurlSpeed($proxy)
    {
        $start = microtime(true);

        $ch = curl_init("http://google.com"); // có thể đổi thành site nhẹ như https://httpbin.org/get
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_PROXY, "{$proxy['ip']}:{$proxy['port']}");
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$proxy['user']}:{$proxy['pass']}");
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // timeout kết nối
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);        // timeout tổng request
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return false; // fail
        }

        $end = microtime(true);
        $timeMs = round(($end - $start) * 1000); // ms

        return $timeMs;
    }
}
