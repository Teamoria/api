<?php

use App\Enums\UploadAccessLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('upload_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('upload_id')->constrained('uploads')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('access_level')->default(UploadAccessLevel::VIEW->value);
            $table->timestamps();
            $table->unique(['upload_id', 'user_id']);
            $table->index(['user_id', 'access_level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_permissions');
    }
};
