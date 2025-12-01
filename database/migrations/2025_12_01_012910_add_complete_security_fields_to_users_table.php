<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCompleteSecurityFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Add security fields for PBKDF2 password hashing and authentication controls
            // Using names that match the existing migration expectations
            $table->string('password_salt', 255)->nullable()->after('password');
            $table->integer('failed_login_attempts')->default(0)->after('password_salt');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            
            // Add role relationship (foreign key to roles table)
            $table->foreignId('role_id')->nullable()->constrained('roles')->onDelete('set null')->after('locked_until');
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
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'password_salt',
                'failed_login_attempts',
                'locked_until',
                'role_id'
            ]);
        });
    }
}
