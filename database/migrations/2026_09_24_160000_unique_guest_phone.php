<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('guests')->where('phone', '')->update(['phone' => null]);

        $groups = DB::table('guests')
            ->select('property_id', 'phone_prefix', 'phone')
            ->whereNotNull('phone')
            ->groupBy('property_id', 'phone_prefix', 'phone')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $row) {
            $memberIds = DB::table('guests')
                ->where('property_id', $row->property_id)
                ->where('phone', $row->phone)
                ->when(
                    $row->phone_prefix === null,
                    fn ($query) => $query->whereNull('phone_prefix'),
                    fn ($query) => $query->where('phone_prefix', $row->phone_prefix),
                )
                ->orderBy('id')
                ->pluck('id');

            if ($memberIds->count() < 2) {
                continue;
            }

            $keepId = (int) (DB::table('reservations')
                ->selectRaw('guest_id, COUNT(*) as stay_count')
                ->whereIn('guest_id', $memberIds)
                ->groupBy('guest_id')
                ->orderByDesc('stay_count')
                ->orderBy('guest_id')
                ->value('guest_id') ?? $memberIds->first());

            $dropIds = $memberIds
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $keepId)
                ->values();

            if ($dropIds->isEmpty()) {
                continue;
            }

            DB::table('reservations')->whereIn('guest_id', $dropIds)->update(['guest_id' => $keepId]);
            DB::table('guests')->whereIn('id', $dropIds)->delete();
        }

        Schema::table('guests', function (Blueprint $table) {
            $table->unique(['property_id', 'phone_prefix', 'phone'], 'guests_property_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropUnique('guests_property_phone_unique');
        });
    }
};
