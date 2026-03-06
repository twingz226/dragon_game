<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Identify and fix duplicate names
        $duplicates = DB::table('users')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(name) > 1')
            ->pluck('name');

        foreach ($duplicates as $duplicateName) {
            $users = DB::table('users')->where('name', $duplicateName)->orderBy('id')->get();
            
            // Keep the first one, update the rest
            $users->shift(); 
            
            $counter = 1;
            foreach ($users as $user) {
                $newName = $duplicateName . '_' . $counter;
                // Add an extra check just in case the new name is also taken
                while (DB::table('users')->where('name', $newName)->exists()) {
                    $counter++;
                    $newName = $duplicateName . '_' . $counter;
                }
                
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['name' => $newName]);
                
                $counter++;
            }
        }

        // 2. Add the unique constraint
        Schema::table('users', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
