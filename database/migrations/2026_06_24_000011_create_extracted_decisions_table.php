<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('extracted_decisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('meeting_summary_id')->constrained('meeting_summaries')->cascadeOnDelete();
            $table->text('decision_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extracted_decisions');
    }
};
