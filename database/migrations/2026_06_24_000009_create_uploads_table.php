<?php

use App\Enums\FileCategory;
use App\Enums\UploadScope;
use App\Enums\UploadStatus;
use App\Enums\UploadVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('task_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('scope', UploadScope::cases());
            $table->enum('visibility', UploadVisibility::cases())
                ->default(UploadVisibility::PRIVATE->value);
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->enum('category', FileCategory::cases());
            $table->unsignedBigInteger('file_size')->default(0);
            $table->enum('status', UploadStatus::cases())->default(UploadStatus::PENDING->value);
            $table->timestamp('upload_date')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'scope']);
            $table->index(['project_id', 'visibility']);
            $table->index(['task_id', 'visibility']);
            $table->index(['user_id', 'created_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
