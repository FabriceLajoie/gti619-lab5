<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSecurityConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('security_configs', function (Blueprint $table) {
            $table->id();
            
            // Authentication security parameters
            $table->integer('max_login_attempts')->default(5);
            $table->integer('lockout_duration_minutes')->default(30);
            
            // Password policy parameters
            $table->integer('password_min_length')->default(12);
            $table->boolean('password_require_uppercase')->default(true);
            $table->boolean('password_require_lowercase')->default(true);
            $table->boolean('password_require_numbers')->default(true);
            $table->boolean('password_require_special')->default(true);
            $table->integer('password_history_count')->default(5);
            $table->integer('password_expiry_days')->default(90);
            
            // PBKDF2 hashing parameters
            $table->integer('pbkdf2_iterations')->default(100000);
            
            // Session security parameters
            $table->integer('session_timeout_minutes')->default(120);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('security_configs');
    }
}
