<?php

namespace Database\Seeders;

use App\Enums\ChargeCategoryCode;
use App\Models\ChargeCategory;
use App\Models\Property;
use Illuminate\Database\Seeder;

class ChargeCategorySeeder extends Seeder
{
    public function run(): void
    {
        Property::query()->each(fn (Property $property) => $this->seedFor($property));
    }

    public function seedFor(Property $property): void
    {
        foreach (ChargeCategoryCode::cases() as $code) {
            ChargeCategory::query()->updateOrCreate(
                [
                    'property_id' => $property->id,
                    'code' => $code->value,
                ],
                [
                    'name' => $code->label(),
                    'is_room' => $code->isRoom(),
                    'is_active' => true,
                    'sort_order' => $code->sortOrder(),
                ],
            );
        }
    }
}
