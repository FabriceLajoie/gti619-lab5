<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAuditLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100); // e.g., 'login_success', 'login_failure', 'password_change'
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('ip_address', 45); // Support both IPv4 and IPv6
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable(); // Additional event-specific data
            $table->string('session_id', 255)->nullable();
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['event_type', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
}
