<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->text('value')->nullable();
        });

        Schema::create('admin_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 160)->unique();
            $table->string('password');
            $table->string('role', 20)->default('operador');
            $table->text('totp_secret')->nullable();
            $table->dateTime('totp_confirmed_at')->nullable();
            $table->boolean('active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('admin_users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at');
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 11)->unique();
            $table->text('cpf_encrypted');
            $table->char('cpf_hash', 64)->unique();
            $table->string('name', 120);
            $table->date('birth_date');
            $table->string('email', 160)->nullable();
            $table->string('city', 80)->nullable();
            $table->char('state', 2)->nullable();
            $table->boolean('location_consent')->default(false);
            $table->dateTime('terms_accepted_at')->nullable();
            $table->dateTime('blocked_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');
            $table->dateTime('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('self_exclusions', function (Blueprint $table) {
            $table->id();
            $table->char('cpf_hash', 64)->index();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period', 20);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_exclusions');
        Schema::dropIfExists('notices');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('admin_users');
        Schema::dropIfExists('settings');
    }
};
