<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_user', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user');
    }
};
