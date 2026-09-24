<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->string('provider_code')->nullable()->after('property_id');
            $table->longText('config_encrypted')->nullable()->after('status');
            $table->boolean('is_active')->default(true)->after('config_encrypted');
        });

        DB::table('channel_connections')->whereNull('provider_code')->update([
            'provider_code' => DB::raw('provider'),
        ]);

        Schema::table('channel_connections', function (Blueprint $table) {
            $table->unique(['property_id', 'provider_code']);
        });
    }

    public function down(): void
    {
        Schema::table('channel_connections', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'provider_code']);
            $table->dropColumn(['provider_code', 'config_encrypted', 'is_active']);
        });
    }
};
