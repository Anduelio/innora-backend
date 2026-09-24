<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('default_check_in_time', 5)->default('14:00');
            $table->string('default_check_out_time', 5)->default('11:00');
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->string('name')->default('');
            $table->text('description')->nullable();
            $table->string('short_description')->nullable();
            $table->unsignedTinyInteger('base_occupancy')->default(2);
            $table->unsignedTinyInteger('max_adults')->default(4);
            $table->unsignedTinyInteger('max_children')->default(2);
            $table->unsignedTinyInteger('max_occupancy')->default(6);
            $table->unsignedInteger('base_price_cents')->default(0);
            $table->unsignedSmallInteger('size_m2')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->string('floor')->nullable();
            $table->string('building')->nullable();
            $table->string('internal_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('operational_status')->default('ready');
        });

        Schema::table('room_blocks', function (Blueprint $table) {
            $table->string('type')->default('maintenance');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->string('expected_departure', 5)->nullable();
        });

        Schema::create('bed_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('room_type_beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bed_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quantity');
            $table->timestamps();
            $table->unique(['room_type_id', 'bed_type_id']);
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('category');
            $table->timestamps();
        });

        Schema::create('amenity_room_type', function (Blueprint $table) {
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->primary(['room_type_id', 'amenity_id']);
        });

        Schema::create('room_type_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_cover')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('channel_room_type_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('external_room_type_id');
            $table->string('external_rate_plan_id')->nullable();
            $table->timestamps();
            $table->unique(['property_id', 'provider', 'external_room_type_id'], 'channel_room_type_external_unique');
        });

        Schema::create('room_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        DB::table('room_types')->update([
            'name' => DB::raw('ui_type'),
            'base_occupancy' => DB::raw('capacity'),
            'max_adults' => DB::raw('capacity'),
            'max_occupancy' => DB::raw('capacity'),
        ]);

        foreach ([
            'Dyshe' => ['Dhomë Dyshe', 22, 8000],
            'Teke' => ['Dhomë Teke', 16, 5500],
            'Suitë' => ['Suitë', 32, 14000],
            'Familjare' => ['Dhomë Familjare', 36, 11000],
        ] as $ui => [$name, $size, $price]) {
            DB::table('room_types')->where('ui_type', $ui)->update([
                'name' => $name,
                'size_m2' => $size,
                'base_price_cents' => $price,
            ]);
        }

        foreach (DB::table('rooms')->select('id', 'number')->get() as $room) {
            $floor = preg_match('/^\d/', (string) $room->number) === 1 ? substr((string) $room->number, 0, 1) : null;
            DB::table('rooms')->where('id', $room->id)->update([
                'floor' => $floor,
                'operational_status' => 'ready',
                'is_active' => true,
            ]);
        }

        (new \Database\Seeders\InventoryCatalogSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('room_events');
        Schema::dropIfExists('channel_room_type_maps');
        Schema::dropIfExists('room_type_images');
        Schema::dropIfExists('amenity_room_type');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('room_type_beds');
        Schema::dropIfExists('bed_types');

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'checked_out_at', 'expected_departure']);
        });

        Schema::table('room_blocks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['type', 'notes']);
        });

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['floor', 'building', 'internal_name', 'notes', 'is_active', 'operational_status']);
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'description', 'short_description', 'base_occupancy', 'max_adults', 'max_children',
                'max_occupancy', 'base_price_cents', 'size_m2', 'is_active', 'sort_order',
            ]);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['default_check_in_time', 'default_check_out_time']);
        });
    }
};
