<?php

namespace App\Managers\Billing;

use App\Enums\PermissionEnum;
use App\Models\ChargeCategory;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\User;
use App\Traits\HasMessages;
use Illuminate\Support\Facades\DB;

class ChargeManager
{
    use HasMessages;

    public function __construct(private readonly FolioManager $folios) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function add(User $actor, Folio $folio, array $data): FolioItem
    {
        $this->folios->assertPermission($actor, PermissionEnum::FoliosAddCharge);
        $this->folios->assertOwned($actor, $folio);
        $this->folios->assertOpen($folio);

        $category = ChargeCategory::query()
            ->where('property_id', $folio->property_id)
            ->where('id', $data['chargeCategoryId'])
            ->where('is_active', true)
            ->first();

        if ($category === null) {
            $this->throwValidationError('chargeCategoryId', 'messages.folios.category_not_found');
        }

        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $unit = (int) $data['unitCents'];
        $amount = $quantity * $unit;

        return DB::transaction(function () use ($actor, $folio, $category, $data, $quantity, $unit, $amount) {
            $item = FolioItem::query()->create([
                'property_id' => $folio->property_id,
                'folio_id' => $folio->id,
                'charge_category_id' => $category->id,
                'category_code' => $category->code,
                'description' => trim((string) $data['description']),
                'room_number' => $data['roomNumber'] ?? $folio->reservation?->stay?->room?->number,
                'service_date' => $data['serviceDate'] ?? now()->toDateString(),
                'quantity' => $quantity,
                'unit_cents' => $unit,
                'amount_cents' => $amount,
                'is_room_charge' => (bool) $category->is_room,
                'posted_by' => $actor->id,
            ]);

            $this->folios->recalculate($folio);
            $this->folios->recordEvent($folio, $actor, 'charge.created', FolioItem::class, $item->id);

            return $item->refresh();
        });
    }

    public function void(User $actor, FolioItem $item, string $reason): FolioItem
    {
        $this->folios->assertPermission($actor, PermissionEnum::FoliosVoidCharge);
        $folio = $item->folio;
        $this->folios->assertOwned($actor, $folio);
        $this->folios->assertOpen($folio);

        if ($item->isVoided()) {
            $this->throwValidationError('charge', 'messages.folios.charge_already_voided');
        }

        if (trim($reason) === '') {
            $this->throwValidationError('reason', 'messages.folios.void_reason_required');
        }

        return DB::transaction(function () use ($actor, $item, $folio, $reason) {
            $item->voided_at = now();
            $item->voided_by = $actor->id;
            $item->void_reason = $reason;
            $item->save();

            $this->folios->recalculate($folio);
            $this->folios->recordEvent($folio, $actor, 'charge.voided', FolioItem::class, $item->id, $reason);

            return $item->refresh();
        });
    }
}
