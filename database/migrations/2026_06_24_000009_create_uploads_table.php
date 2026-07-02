<?php

use App\Enums\FileCategory;
use App\Enums\UploadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->enum('category', FileCategory::cases());
            $table->unsignedBigInteger('file_size')->default(0);
            $table->enum('status', UploadStatus::cases())->default(UploadStatus::PENDING->value);
            $table->timestamp('upload_date')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
