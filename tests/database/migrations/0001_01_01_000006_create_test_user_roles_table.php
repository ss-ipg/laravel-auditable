<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_user_roles', static function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('test_users');
            $table->foreignId('role_id')->constrained('test_roles');
            $table->string('assigned_by')->nullable();
        });
    }
};
