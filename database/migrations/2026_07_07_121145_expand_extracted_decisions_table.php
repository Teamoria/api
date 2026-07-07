<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extracted_decisions', function (Blueprint $table) {
            $table->string('title')->nullable()->after('decision_text');
            $table->text('description')->nullable()->after('title');
            $table->string('confidence')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('extracted_decisions', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'confidence']);
        });
    }
};
