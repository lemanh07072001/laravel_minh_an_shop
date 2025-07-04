<?php

namespace App\Console\Commands;

use App\Helpers\ProxyHelper;
use Illuminate\Console\Command;

class FetchProxyCN extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature =  'proxy:fetch-cn';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = 500; // Số lượng proxy cần lấy
        $maxAttempts = 5000; // Số lần gọi tối đa

        $this->info("🔍 Bắt đầu lấy tối đa {$limit} proxy với latency <100ms...");

        $proxies = [];
        $attempt = 0;
        $stt = 0;

        // Load danh sách proxy cần bỏ qua
        $skipKeys = [];
        $skipFile = storage_path('app/banned_proxies.txt');
        if (file_exists($skipFile)) {
            $skipLines = file($skipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($skipLines as $line) {
                $parts = explode(':', $line);
                if (count($parts) >= 2) {
                    $skipKeys["{$parts[0]}:{$parts[1]}"] = true;
                }
            }
        }

        $this->newLine();

        while ($attempt < $maxAttempts) {
            $stt++;
            $result = ProxyHelper::fetchAndCheckProxy();

            if ($result['status'] === 'success') {
                $proxy = $result['proxy'];
                $key = "{$proxy['ip']}:{$proxy['port']}";

                if (isset($skipKeys[$key])) {
                    $this->warn("⛔ Proxy nằm trong danh sách blacklist: {$key}");
                } elseif (!isset($proxies[$key])) {
                    // Test xem proxy còn sống không
                    $check = $this->testProxy($proxy['ip'], $proxy['port'], $proxy['user'], $proxy['pass']);

                    if ($check['alive'] && $check['latency'] < 100) {
                        $proxies[$key] = "{$proxy['ip']}:{$proxy['port']}:{$proxy['user']}:{$proxy['pass']}";
                        $this->line("✅ Proxy OK: {$key} - {$check['latency']} ms (STT {$stt})");
                    } else {
                        $this->warn("❌ Proxy không đạt yêu cầu: {$key} - {$check['latency']} ms");
                    }

                    if (count($proxies) >= $limit) {
                        $this->info("🎯 Đã thu thập đủ {$limit} proxy đạt yêu cầu.");
                        break;
                    }
                } else {
                    $this->line("⚠️ Proxy đã lấy trong phiên này: {$key}");
                }
            } else {
                $this->warn("❌ Lần thử {$attempt}: {$result['message']}");
            }

            $attempt++;
            usleep(100000); // Delay 0.1s
        }

        $this->newLine();

        if (empty($proxies)) {
            $this->error("❌ Không có proxy nào đạt yêu cầu.");
            return 1;
        }

        // Ghi file
        $lines = array_values($proxies);
        file_put_contents(storage_path('app/proxies_fast.txt'), implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND);

        $this->info("🎉 Đã thêm " . count($proxies) . " proxy.");
        $this->info("💾 Lưu tại: storage/app/proxies_fast.txt");

        return 0;
    }

    private function testProxy($ip, $port, $user, $pass)
    {
        $url = "https://example.com"; // Trang nhẹ để test
        $start = microtime(true);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_PROXY => "{$ip}:{$port}",
            CURLOPT_PROXYUSERPWD => "{$user}:{$pass}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latency = round((microtime(true) - $start) * 1000); // ms

        return [
            'alive' => $httpCode === 200,
            'latency' => $latency,
        ];
    }




}
