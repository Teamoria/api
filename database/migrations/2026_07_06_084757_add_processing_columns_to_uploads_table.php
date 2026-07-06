<?php

use App\Enums\ProcessingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->enum('processing_status', ProcessingStatus::cases())
                ->default(ProcessingStatus::QUEUED->value)
                ->after('status');
            $table->text('processing_error')->nullable()->after('processing_status');
            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::table('uploads', function (Blueprint $table) {
            $table->dropIndex(['processing_status']);
            $table->dropColumn(['processing_status', 'processing_error']);
        });
    }
};
