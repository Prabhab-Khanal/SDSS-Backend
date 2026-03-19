<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('folders')->cascadeOnDelete();
            $table->string('name', 255);
            $table->timestamps();

            $table->index('user_id');
            $table->index('parent_id');
            $table->unique(['user_id', 'parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folders');
    }
};
