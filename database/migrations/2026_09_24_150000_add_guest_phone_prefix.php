<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('phone_prefix', 8)->nullable()->after('name');
            $table->index(['property_id', 'phone_prefix', 'phone']);
        });

        $prefixes = ['+355', '+383', '+389', '+382', '+381', '+49', '+44', '+39', '+33', '+30', '+1'];
        foreach (DB::table('guests')->whereNull('phone_prefix')->whereNotNull('phone')->get() as $guest) {
            $compact = preg_replace('/\s+/', '', (string) $guest->phone) ?? '';
            foreach ($prefixes as $prefix) {
                if (! str_starts_with($compact, $prefix)) {
                    continue;
                }
                DB::table('guests')->where('id', $guest->id)->update([
                    'phone_prefix' => $prefix,
                    'phone' => substr($compact, strlen($prefix)),
                ]);
                break;
            }
        }
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['property_id', 'phone_prefix', 'phone']);
            $table->dropColumn('phone_prefix');
        });
    }
};
