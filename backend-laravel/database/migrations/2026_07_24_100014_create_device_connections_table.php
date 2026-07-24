<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('provider');
            $table->string('provider_athlete_id');
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('expires_at');
            $table->string('scope')->nullable();
            $table->timestamp('connected_at')->useCurrent();

            $table->unique(['student_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_connections');
    }
};
