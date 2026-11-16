<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Users sign in with a username. Existing rows are backfilled from the part of the email
     * before the "@" (with the id appended on a clash) before the column becomes required.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->after('name');
        });

        $takenUsernames = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'email']) as $user) {
            $username = Str::before($user->email, '@');

            if (in_array($username, $takenUsernames, true)) {
                $username .= $user->id;
            }

            $takenUsernames[] = $username;

            DB::table('users')->where('id', $user->id)->update(['username' => $username]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
