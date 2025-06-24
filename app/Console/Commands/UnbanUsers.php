<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserBan;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UnbanUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:unban-users';

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
        $userBans = UserBan::whereNot('lock_time',User::UserBanText['Vĩnh viễn'])->get();

        foreach ($userBans as $userBan) {
            if (!$userBan->banned_at || !$userBan->lock_time) {
                continue; // tránh lỗi nếu dữ liệu thiếu
            }

            // Thời điểm bắt đầu
            $startTime = Carbon::parse($userBan->banned_at);
            $minutes = (int) $userBan->lock_time;

            $endTime = $startTime->copy()->addMinutes($minutes)->timestamp;

            $timeNow = Carbon::now()->timestamp;

            if ($timeNow >= $endTime) {
                logger("User ID {$userBan->id} đã hết thời gian bị khóa");
                $user = $userBan->user()->first();
                if (!$user) {
                    $userBan->delete();
                }else{
                    $user->update([
                        'status' => User::STAUS_KEY['ACTIVE']
                    ]);

                    $userBan->update([
                        'status' => User::STAUS_KEY['ACTIVE']
                    ]);
                }
            }
        }
    }
}
