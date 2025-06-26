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
        $maxAttempts = 1000; // Số lần gọi tối đa

        $this->info("🔍 Gọi {$maxAttempts} lần API để lấy {$limit} proxy mới nhất...");
        $proxies = [];
        $attempt = 0;
        $this->newLine();
        $stt = 0;

        while ($attempt < $maxAttempts) {
            $stt += 1;
            $result = ProxyHelper::fetchAndCheckProxy();

            if ($result['status'] === 'success') {
                $proxy = $result['proxy'];
                $key = "{$proxy['ip']}:{$proxy['port']}";

                // Tránh trùng proxy dựa trên IP + port
                if (!isset($proxies[$key])) {
                    $proxies[$key] = "{$proxy['ip']}:{$proxy['port']}:{$proxy['user']}:{$proxy['pass']}";
                    $this->line("✅ {$key} :" .$stt);
                } else {
                    $this->line("⚠️ Duplicate proxy: {$key}");
                }
            } else {
                $this->warn("❌ Lần thử {$attempt}: {$result['message']}");
            }

            $attempt++;
            usleep(100000); // Delay 0.1s
        }

        $this->newLine();

        // Lấy 100 proxy mới nhất
        $lastProxies = array_slice(array_values($proxies), -$limit);

        if (empty($lastProxies)) {
            $this->error("❌ Không có proxy hợp lệ nào.");
            return 1;
        }

        file_put_contents(storage_path('app/proxies_fast.txt'), implode(PHP_EOL, $lastProxies));

        $this->info("🎉 Đã lấy được " . count($lastProxies) . " proxy mới nhất.");
        $this->info("💾 Lưu tại: storage/app/proxies_fast.txt");

        return 0;
    }


}
