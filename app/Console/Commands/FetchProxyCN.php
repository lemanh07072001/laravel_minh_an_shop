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
        $limit = 100; // Số lượng proxy cần lấy
        $maxAttempts = 1800; // Số lần gọi tối đa

        $this->info("🔍 Gọi {$maxAttempts} lần API để lấy {$limit} proxy mới nhất, bỏ qua proxy đã tồn tại trong danh sách...");

        $proxies = [];
        $attempt = 0;
        $stt = 0;

        // Load danh sách IP:Port cần bỏ qua
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
                    // Nếu trùng, bỏ qua, không cần báo
                } elseif (!isset($proxies[$key])) {
                    // Proxy mới
                    $proxies[$key] = "{$proxy['ip']}:{$proxy['port']}:{$proxy['user']}:{$proxy['pass']}";
                    $this->line("✅ Proxy mới: {$key} (STT {$stt})");

                    if (count($proxies) >= $limit) {
                        $this->info("🎯 Đã thu thập đủ {$limit} proxy mới.");
                        break;
                    }
                }
                // Nếu proxy trùng trong phiên này, cũng bỏ qua
            }

            $attempt++;
            usleep(100000); // Delay 0.1s
        }

        $this->newLine();

        if (empty($proxies)) {
            $this->error("❌ Không có proxy mới nào.");
            return 1;
        }

        // Ghi file
        $lines = array_values($proxies);
        file_put_contents(storage_path('app/banned_proxies.txt'), implode(PHP_EOL, $lines) . PHP_EOL, FILE_APPEND);

        $this->info("🎉 Đã thêm " . count($proxies) . " proxy mới.");
        $this->info("💾 Lưu tại: storage/app/banned_proxies.txt");

        return 0;
    }





}
