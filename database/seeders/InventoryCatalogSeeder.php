<?php

namespace Database\Seeders;

use App\Models\Amenity;
use App\Models\BedType;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class InventoryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'single', 'double', 'queen', 'king', 'twin', 'sofa_bed', 'bunk', 'crib',
        ] as $code) {
            BedType::query()->updateOrCreate(['code' => $code]);
        }

        foreach ([
            'wifi' => 'general',
            'air_conditioning' => 'general',
            'heating' => 'general',
            'desk' => 'general',
            'soundproof' => 'general',
            'phone' => 'general',
            'shower' => 'bathroom',
            'bathtub' => 'bathroom',
            'hair_dryer' => 'bathroom',
            'jacuzzi' => 'bathroom',
            'tv' => 'entertainment',
            'minibar' => 'kitchen',
            'refrigerator' => 'kitchen',
            'kitchen' => 'kitchen',
            'coffee_machine' => 'kitchen',
            'balcony' => 'view',
            'sea_view' => 'view',
            'accessible' => 'accessibility',
            'safe' => 'safety',
            'iron' => 'general',
        ] as $code => $category) {
            Amenity::query()->updateOrCreate(['code' => $code], ['category' => $category]);
        }

        $beds = [
            'Dyshe' => ['double' => 1],
            'Teke' => ['single' => 1],
            'Suitë' => ['king' => 1],
            'Familjare' => ['double' => 1, 'sofa_bed' => 1],
        ];
        $amenities = [
            'Dyshe' => ['wifi', 'air_conditioning', 'tv', 'shower'],
            'Teke' => ['wifi', 'air_conditioning', 'tv', 'shower'],
            'Suitë' => ['wifi', 'air_conditioning', 'tv', 'minibar', 'balcony', 'safe', 'bathtub'],
            'Familjare' => ['wifi', 'air_conditioning', 'tv', 'balcony'],
        ];

        foreach (RoomType::query()->get() as $type) {
            if ($type->bedTypes()->exists()) {
                continue;
            }
            foreach ($beds[$type->ui_type] ?? [] as $code => $quantity) {
                $bed = BedType::query()->where('code', $code)->first();
                if ($bed !== null) {
                    $type->bedTypes()->attach($bed->id, ['quantity' => $quantity]);
                }
            }
            $ids = Amenity::query()->whereIn('code', $amenities[$type->ui_type] ?? [])->pluck('id');
            $type->amenities()->syncWithoutDetaching($ids);
        }
    }
}
