<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSecurityFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Add remaining security fields for PBKDF2 password hashing
            // password_salt, failed_login_attempts, and locked_until are added in previous migration
            $table->string('password_hash', 255)->nullable()->after('password_salt');
            $table->boolean('must_change_password')->default(false)->after('locked_until');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_hash',
                'must_change_password'
            ]);
        });
    }
}
