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
    protected $signature = 'proxy:fetch-cn {--days=1}';

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
        $daysToRun = (int) $this->option('days'); // Số ngày chạy
        if ($daysToRun < 1) {
            $this->error("❌ Bạn phải nhập số ngày >= 1.");
            return 1;
        }

        $this->info("🔍 Bắt đầu chạy liên tục trong {$daysToRun} ngày để thu thập proxy...");
        $this->newLine();

        $proxies = [];
        $stt = 0;

        // Load danh sách blacklist
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

        // Thời gian bắt đầu
        $startTime = time();
        $endTime = $startTime + ($daysToRun * 86400); // 86400 giây = 1 ngày

        while (true) {
            // Kiểm tra hết thời gian chưa
            if (time() >= $endTime) {
                $this->info("⏰ Đã hết thời gian chạy {$daysToRun} ngày.");
                break;
            }

            $stt++;
            $result = ProxyHelper::fetchAndCheckProxy();

            if ($result['status'] === 'success') {
                $proxy = $result['proxy'];
                $key = "{$proxy['ip']}:{$proxy['port']}";

                if (isset($skipKeys[$key])) {
                    $this->warn("⛔ Proxy nằm trong blacklist: {$key}");
                } elseif (!isset($proxies[$key])) {
                    // Test proxy
                    $check = $this->testProxy($proxy['ip'], $proxy['port'], $proxy['user'], $proxy['pass']);

                    if ($check['alive']) {
                        $proxyLine = "{$proxy['ip']}:{$proxy['port']}:{$proxy['user']}:{$proxy['pass']}";
                        $proxies[$key] = $proxyLine;

                        // ✅ Lưu ngay vào checklist
                        file_put_contents(
                            storage_path('app/checklist_proxies.txt'),
                            $proxyLine . PHP_EOL,
                            FILE_APPEND
                        );

                        $this->line("✅ Proxy OK: {$key} (STT {$stt})");
                    } else {
                        $this->warn("❌ Proxy không hoạt động: {$key}");
                    }

                    // Nếu đã đủ 100 proxy thì lưu ngay
                    if (count($proxies) >= 100) {
                        $lines = array_values($proxies);
                        file_put_contents(storage_path('app/proxies_fast.txt'), implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND);

                        $this->info("💾 Đã lưu 100 proxy vào file. Tiếp tục thu thập...");
                        $proxies = []; // Xóa proxy đã lưu để thu thập tiếp
                    }
                } else {
                    $this->line("⚠️ Proxy đã thu thập trong phiên này: {$key}");
                }
            }

            usleep(100000); // Delay 0.1s
        }

        // Nếu còn proxy chưa đủ 100, lưu nốt
        if (!empty($proxies)) {
            $lines = array_values($proxies);
            file_put_contents(storage_path('app/proxies_fast.txt'), implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND);

            $this->info("💾 Đã lưu " . count($proxies) . " proxy còn lại.");
        }

        $this->info("🎉 Hoàn thành quá trình thu thập proxy.");
        return 0;
    }


    private function testProxy($ip, $port, $user, $pass)
    {
        $url = "https://example.com";
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

        return [
            'alive' => $httpCode === 200,
        ];
    }




}
