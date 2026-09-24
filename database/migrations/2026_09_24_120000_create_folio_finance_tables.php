<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('gross_cents')->nullable()->after('paid_cents');
            $table->unsignedInteger('commission_cents')->nullable()->after('gross_cents');
            $table->unsignedInteger('net_cents')->nullable()->after('commission_cents');
        });

        Schema::create('charge_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_room')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['property_id', 'code']);
        });

        Schema::create('folios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('status')->default('open');
            $table->unsignedInteger('charges_cents')->default(0);
            $table->unsignedInteger('payments_cents')->default(0);
            $table->integer('balance_cents')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['property_id', 'number']);
            $table->unique(['reservation_id']);
            $table->index(['property_id', 'status']);
        });

        Schema::create('folio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_category_id')->constrained()->restrictOnDelete();
            $table->string('category_code');
            $table->string('description');
            $table->string('room_number')->nullable();
            $table->date('service_date');
            $table->unsignedInteger('quantity')->default(1);
            $table->integer('unit_cents');
            $table->integer('amount_cents');
            $table->boolean('is_room_charge')->default(false);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'service_date', 'category_code']);
            $table->index(['folio_id', 'voided_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->string('method');
            $table->integer('amount_cents');
            $table->char('currency', 3)->default('EUR');
            $table->timestamp('paid_at');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('refund_of_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'paid_at', 'method']);
            $table->index(['folio_id', 'voided_at']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->constrained()->cascadeOnDelete();
            $table->integer('amount_cents');
            $table->timestamps();
            $table->index(['folio_id']);
        });

        Schema::create('financial_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['property_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_events');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('folio_items');
        Schema::dropIfExists('folios');
        Schema::dropIfExists('charge_categories');

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['gross_cents', 'commission_cents', 'net_cents']);
        });
    }
};
