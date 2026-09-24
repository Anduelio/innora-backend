<?php

use App\Http\Controllers\AmenityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelConnectionController;
use App\Http\Controllers\FolioController;
use App\Http\Controllers\GuestController;
use App\Http\Controllers\HotelSettingsController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomBlockController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTypeController;
use App\Http\Controllers\SyncController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/auth/password', [AuthController::class, 'password']);

    Route::get('/settings/hotel', [HotelSettingsController::class, 'show']);
    Route::put('/settings/hotel', [HotelSettingsController::class, 'update']);

    Route::get('/channels/catalog', [ChannelConnectionController::class, 'catalog']);
    Route::get('/channels', [ChannelConnectionController::class, 'index']);
    Route::post('/channels', [ChannelConnectionController::class, 'store']);
    Route::delete('/channels/{channelConnection}', [ChannelConnectionController::class, 'destroy']);

    Route::get('/amenities', [AmenityController::class, 'index']);
    Route::get('/room-types', [RoomTypeController::class, 'index']);
    Route::post('/room-types', [RoomTypeController::class, 'store']);
    Route::get('/room-types/{roomType}', [RoomTypeController::class, 'show']);
    Route::put('/room-types/{roomType}', [RoomTypeController::class, 'update']);

    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms/bulk', [RoomController::class, 'bulk']);
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::put('/rooms/{number}', [RoomController::class, 'update']);
    Route::post('/rooms/{number}/status', [RoomController::class, 'status']);
    Route::post('/rooms/{number}/blocks', [RoomController::class, 'block']);
    Route::delete('/room-blocks/{roomBlock}', [RoomBlockController::class, 'destroy']);
    Route::get('/guests', [GuestController::class, 'index']);

    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::patch('/reservations/{reservation}', [ReservationController::class, 'update']);
    Route::post('/reservations/{reservation}/assign', [ReservationController::class, 'assign']);
    Route::post('/reservations/{reservation}/check-in', [ReservationController::class, 'checkIn']);
    Route::post('/reservations/{reservation}/check-out', [ReservationController::class, 'checkOut']);
    Route::post('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);

    Route::get('/charge-categories', [FolioController::class, 'categories']);
    Route::get('/reservations/{reservation}/folio', [FolioController::class, 'showForReservation'])
        ->middleware('permission:folios.view');
    Route::get('/folios/{folio}', [FolioController::class, 'show'])
        ->middleware('permission:folios.view');
    Route::post('/folios/{folio}/charges', [FolioController::class, 'addCharge'])
        ->middleware('permission:folios.add_charge');
    Route::post('/folio-items/{folioItem}/void', [FolioController::class, 'voidCharge'])
        ->middleware('permission:folios.void_charge');
    Route::post('/folios/{folio}/payments', [FolioController::class, 'addPayment'])
        ->middleware('permission:folios.record_payment');
    Route::post('/payments/{payment}/void', [FolioController::class, 'voidPayment'])
        ->middleware('permission:folios.refund_payment');

    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
        Route::get('/reports/revenue', [ReportController::class, 'revenue']);
        Route::get('/reports/payments', [ReportController::class, 'payments']);
        Route::get('/reports/outstanding', [ReportController::class, 'outstanding']);
        Route::get('/reports/source', [ReportController::class, 'source']);
        Route::get('/reports/daily', [ReportController::class, 'daily']);
        Route::get('/reports/occupancy', [ReportController::class, 'occupancy']);
        Route::get('/reports/channel', [ReportController::class, 'channel']);
    });
    Route::get('/reports/export/{type}', [ReportController::class, 'export'])
        ->middleware('permission:reports.export');

    // Desk dashboard tile: reception may see outstanding totals without full reports.
    Route::get('/desk/outstanding', [ReportController::class, 'dashboard'])
        ->middleware('permission:folios.view|reports.view');

    Route::get('/sync/status', [SyncController::class, 'status']);
    Route::post('/sync/retry', [SyncController::class, 'retry']);
    Route::post('/sync/fail', [SyncController::class, 'fail']);
});
