<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->timestamp('follow_up_reminder_sms_sent_at')->nullable()->after('follow_up_note');
            $table->timestamp('follow_up_reminder_whatsapp_queued_at')->nullable()->after('follow_up_reminder_sms_sent_at');
            $table->timestamp('follow_up_reminder_whatsapp_sent_at')->nullable()->after('follow_up_reminder_whatsapp_queued_at');
        });
    }

    public function down(): void
    {
        Schema::table('visit_records', function (Blueprint $table) {
            $table->dropColumn([
                'follow_up_reminder_sms_sent_at',
                'follow_up_reminder_whatsapp_queued_at',
                'follow_up_reminder_whatsapp_sent_at',
            ]);
        });
    }
};
