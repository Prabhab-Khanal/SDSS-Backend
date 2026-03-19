<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->string('name', 255);
            $table->string('original_name', 255);
            $table->string('storage_path', 512);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index('user_id');
            $table->index('folder_id');
            $table->unique(['user_id', 'folder_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
