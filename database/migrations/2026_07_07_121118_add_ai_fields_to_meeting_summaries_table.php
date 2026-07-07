<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_summaries', function (Blueprint $table) {
            $table->string('source_type')->nullable()->after('upload_id');
            $table->json('structured_summary')->nullable()->after('summary');
            $table->json('transcript_quality')->nullable()->after('transcript');
            $table->unsignedInteger('indexed_chunk_count')->default(0)->after('structured_summary');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_summaries', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'structured_summary', 'transcript_quality', 'indexed_chunk_count']);
        });
    }
};
