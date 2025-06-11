<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_bans', function (Blueprint $table) {
            $table->id();
             $table->integer('user_id');
            $table->string('reason')->nullable()->comment('Lý do bị cấm');
            $table->timestamp('banned_at')->nullable(); // Ngày banned
            $table->timestamp('unbanned_at')->nullable(); // Ngày Unbanned
            $table->integer('banned_by')->nullable()->comment('Người ban'); // nếu cần lưu admin nào ban
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_bans');
    }
};
