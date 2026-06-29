<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('meeting_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('upload_id')->constrained('uploads')->cascadeOnDelete();
            $table->longText('transcript')->nullable();
            $table->longText('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_summaries');
    }
};
