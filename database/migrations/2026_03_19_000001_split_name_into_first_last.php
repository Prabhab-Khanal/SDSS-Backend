<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('id')->default('');
            $table->string('last_name')->after('first_name')->default('');
        });

        // Migrate existing data: split 'name' into first_name + last_name
        DB::table('users')->get()->each(function ($user) {
            $parts = explode(' ', $user->name, 2);
            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $parts[0],
                'last_name'  => $parts[1] ?? '',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id')->default('');
        });

        DB::table('users')->get()->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'name' => trim($user->first_name . ' ' . $user->last_name),
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
