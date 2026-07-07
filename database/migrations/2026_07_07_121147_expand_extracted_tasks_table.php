<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extracted_tasks', function (Blueprint $table) {
            $table->string('title')->nullable()->after('task_text');
            $table->text('description')->nullable()->after('title');
            $table->string('category')->nullable()->after('description');
            $table->string('priority')->nullable()->after('category');
            $table->string('assignee')->nullable()->after('priority');
            $table->string('status')->default('pending')->after('assignee');
        });
    }

    public function down(): void
    {
        Schema::table('extracted_tasks', function (Blueprint $table) {
            $table->dropColumn(['title', 'description', 'category', 'priority', 'assignee', 'status']);
        });
    }
};
