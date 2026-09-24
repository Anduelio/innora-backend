<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignReservationRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Managers\Reservations\ReservationManager;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationManager $reservations) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->reservations->list($request->user(), $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'no_pagination' => ['nullable'],
        ]));

        return $this->getJsonResponse($this->fetchResults($query), ReservationResource::class);
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $reservation = $this->reservations->create($request->user(), $request->validated());

        return $this->getJsonResponse($reservation, ReservationResource::class, 'messages.success.reservation_created', true);
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $reservation = $this->reservations->update($request->user(), $reservation, $request->validated());

        return $this->getJsonResponse($reservation, ReservationResource::class, 'messages.success.reservation_updated', true);
    }

    public function checkIn(Request $request, Reservation $reservation): JsonResponse
    {
        $reservation = $this->reservations->checkIn($request->user(), $reservation);

        return $this->getJsonResponse($reservation, ReservationResource::class, 'messages.success.checked_in', true);
    }

    public function checkOut(Request $request, Reservation $reservation): JsonResponse
    {
        $reservation = $this->reservations->checkOut($request->user(), $reservation);

        return $this->getJsonResponse($reservation, ReservationResource::class, 'messages.success.checked_out', true);
    }

    public function assign(AssignReservationRequest $request, Reservation $reservation): JsonResponse
    {
        $reservation = $this->reservations->assign($request->user(), $reservation, $request->validated('roomId'));

        return $this->getJsonResponse($reservation, ReservationResource::class, 'messages.success.reservation_updated', true);
    }

    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        $reservation = $this->reservations->cancel($request->user(), $reservation);

        return $this->getJsonResponse($reservation, ReservationResource::class, 'messages.success.reservation_cancelled', true);
    }
}
