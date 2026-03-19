<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('message')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('requester_id');
            $table->index('file_id');
            $table->index('status');
        });

        // Partial unique index: only one pending request per user+file
        DB::statement('CREATE UNIQUE INDEX access_requests_pending_unique ON access_requests(requester_id, file_id) WHERE status = \'pending\'');
    }

    public function down(): void
    {
        Schema::dropIfExists('access_requests');
    }
};
