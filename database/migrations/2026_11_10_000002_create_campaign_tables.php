<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title', 120);
            $table->string('subtitle', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('rascunho')->index();

            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('total_numbers');
            $table->unsignedTinyInteger('digits');
            $table->unsignedInteger('min_per_order')->default(1);
            $table->unsignedInteger('max_per_order')->default(100);
            $table->json('packages')->nullable();
            $table->unsignedSmallInteger('reservation_minutes')->default(15);

            $table->unsignedInteger('sold_count')->default(0);
            $table->unsignedInteger('reserved_count')->default(0);

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('draw_at')->nullable();
            $table->text('draw_criterion')->nullable();
            $table->string('delivery_deadline', 200)->nullable();
            $table->string('official_result', 120)->nullable();
            $table->unsignedInteger('winning_number')->nullable();
            $table->dateTime('drawn_at')->nullable();

            $table->string('legal_company', 160)->nullable();
            $table->string('legal_cnpj', 14)->nullable();
            $table->string('legal_modality', 30)->nullable();
            $table->string('legal_process', 80)->nullable();
            $table->string('legal_capitalization_company', 160)->nullable();
            $table->string('legal_beneficiary', 160)->nullable();
            $table->string('legal_regulation_path')->nullable();

            $table->char('instant_hash', 64)->nullable();
            $table->string('instant_salt', 64)->nullable();
            $table->dateTime('instant_revealed_at')->nullable();

            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });

        Schema::create('campaign_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->text('placeholder')->nullable();
            $table->string('alt', 160)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('title', 160);
            $table->string('description', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->char('code', 10)->unique();
            $table->foreignId('campaign_id')->constrained();
            $table->foreignId('customer_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('amount_cents');
            $table->string('status', 20)->default('pendente');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->string('close_reason', 200)->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['campaign_id', 'status', 'paid_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_id', 120);
            $table->string('status', 20)->default('pendente');
            $table->unsignedInteger('amount_cents');
            $table->text('pix_payload')->nullable();
            $table->json('raw')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_id']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->dateTime('created_at');
            $table->unique(['campaign_id', 'number']);
        });

        Schema::create('instant_prizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('prize', 160);
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('won_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'number']);
        });

        Schema::create('timed_promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('title', 120);
            $table->text('rules')->nullable();
            $table->string('prize', 160);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->foreignId('winner_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamps();
            $table->index(['campaign_id', 'ends_at']);
        });

        Schema::create('winners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('principal');
            $table->string('display_name', 60);
            $table->string('prize', 160);
            $table->unsignedInteger('number');
            $table->string('city', 80)->nullable();
            $table->char('state', 2)->nullable();
            $table->date('awarded_on');
            $table->dateTime('consent_at')->nullable();
            $table->dateTime('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 11);
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 80);
            $table->text('message');
            $table->char('ip_hash', 64)->nullable();
            $table->dateTime('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['contact_messages', 'winners', 'timed_promos', 'instant_prizes', 'tickets', 'payments', 'orders', 'prizes', 'campaign_images', 'campaigns'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
