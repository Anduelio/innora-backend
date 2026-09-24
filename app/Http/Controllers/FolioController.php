<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolioChargeRequest;
use App\Http\Requests\StoreFolioPaymentRequest;
use App\Http\Requests\VoidFolioItemRequest;
use App\Http\Resources\ChargeCategoryResource;
use App\Http\Resources\FolioItemResource;
use App\Http\Resources\FolioResource;
use App\Http\Resources\PaymentResource;
use App\Managers\Billing\ChargeManager;
use App\Managers\Billing\FolioManager;
use App\Managers\Billing\PaymentManager;
use App\Models\ChargeCategory;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolioController extends Controller
{
    public function __construct(
        private readonly FolioManager $folios,
        private readonly ChargeManager $charges,
        private readonly PaymentManager $payments,
    ) {}

    public function categories(Request $request): JsonResponse
    {
        $this->folios->ensureCategories((int) $request->user()->property_id);
        $rows = ChargeCategory::query()
            ->where('property_id', $request->user()->property_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return $this->getJsonResponse($rows, ChargeCategoryResource::class);
    }

    public function showForReservation(Request $request, Reservation $reservation): JsonResponse
    {
        if ((int) $reservation->property_id !== (int) $request->user()->property_id) {
            abort(404);
        }

        $folio = $this->folios->show($request->user(), $this->folios->forReservation($reservation));

        return $this->getJsonResponse($folio, FolioResource::class, asSingleModel: true);
    }

    public function show(Request $request, Folio $folio): JsonResponse
    {
        $folio = $this->folios->show($request->user(), $folio);

        return $this->getJsonResponse($folio, FolioResource::class, asSingleModel: true);
    }

    public function addCharge(StoreFolioChargeRequest $request, Folio $folio): JsonResponse
    {
        $this->folios->assertOwned($request->user(), $folio);
        $item = $this->charges->add($request->user(), $folio, $request->validated());

        return $this->getJsonResponse($item, FolioItemResource::class, 'messages.folios.charge_added', true);
    }

    public function voidCharge(VoidFolioItemRequest $request, FolioItem $folioItem): JsonResponse
    {
        $item = $this->charges->void($request->user(), $folioItem, $request->validated('reason'));

        return $this->getJsonResponse($item, FolioItemResource::class, 'messages.folios.charge_voided', true);
    }

    public function addPayment(StoreFolioPaymentRequest $request, Folio $folio): JsonResponse
    {
        $this->folios->assertOwned($request->user(), $folio);
        $payment = $this->payments->record($request->user(), $folio, $request->validated());

        return $this->getJsonResponse($payment, PaymentResource::class, 'messages.folios.payment_recorded', true);
    }

    public function voidPayment(VoidFolioItemRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->payments->void($request->user(), $payment, $request->validated('reason'));

        return $this->getJsonResponse($payment, PaymentResource::class, 'messages.folios.payment_voided', true);
    }
}
