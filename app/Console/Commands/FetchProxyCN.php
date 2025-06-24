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
    protected $signature =  'proxy:fetch-cn {--limit=10}';

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
        $limit = (int) $this->option('limit');
        $this->info("🔍 Bắt đầu lấy tối đa {$limit} proxy có real curl speed < 100ms...");

        $proxies = [];
        $attempt = 0;
        $maxAttempts = $limit * 20;
        $this->newLine();

        while (count($proxies) < $limit && $attempt < $maxAttempts) {
            $result = ProxyHelper::fetchAndCheckProxy();

            if ($result['status'] === 'success') {
                $proxy = $result['proxy'];

                $timeMs = ProxyHelper::testProxyCurlSpeed($proxy);
                $timeText = $timeMs !== false ? "{$timeMs}ms" : "curl thất bại";

                $this->line("Thử {$attempt}: {$proxy['ip']}:{$proxy['port']}:{$proxy['user']}:{$proxy['pass']} - Curl: {$timeText}");

                if ($timeMs !== false && $timeMs < 100) {
                    $exist = false;
                    foreach ($proxies as $p) {
                        if ($p['ip'] === $proxy['ip']) {
                            $exist = true;
                            break;
                        }
                    }

                    if (!$exist) {
                        $proxies[] = $proxy;
                        $this->info("✅ Đã thêm proxy #".count($proxies).": {$proxy['ip']}:{$proxy['port']}:{$proxy['user']}:{$proxy['pass']} (Curl: {$timeMs}ms)");
                    }
                }
            } else {
                $this->warn("Lần thử {$attempt}: {$result['message']}");
            }

            $attempt++;
            usleep(500000); // 0.5s giữa các lần thử
        }

        if (count($proxies) === 0) {
            $this->error("❌ Không tìm thấy proxy nào curl dưới 100ms.");
            return 1;
        }

        $this->newLine();
        $this->info("🎉 Hoàn thành! Tìm thấy " . count($proxies) . " proxy curl dưới 100ms.");

        foreach ($proxies as $index => $proxy) {
            $this->line("Proxy #".($index+1).": {$proxy['ip']}:{$proxy['port']}:{$proxy['user']}:{$proxy['pass']}");
        }

        file_put_contents(storage_path('app/proxies_fast.txt'), implode(PHP_EOL, array_map(function ($proxy) {
            return "{$proxy['ip']}:{$proxy['port']}:{$proxy['user']}:{$proxy['pass']}";
        }, $proxies)));

        $this->info("💾 Đã lưu vào: storage/app/proxies_fast.txt (dạng ip:port:user:pass)");

        return 0;
    }

}
