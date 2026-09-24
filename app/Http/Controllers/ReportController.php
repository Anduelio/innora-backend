<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRangeRequest;
use App\Reporting\HotelReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly HotelReportService $reports) {}

    public function revenue(ReportRangeRequest $request): JsonResponse
    {
        $data = $this->reports->revenue($request->user(), $request->from(), $request->to());

        return $this->responseToJson('messages.success.the_action_was_completed_successfully', 200, $data);
    }

    public function payments(ReportRangeRequest $request): JsonResponse
    {
        $data = $this->reports->payments($request->user(), $request->from(), $request->to());

        return $this->responseToJson('messages.success.the_action_was_completed_successfully', 200, $data);
    }

    public function outstanding(Request $request): JsonResponse
    {
        return $this->responseToJson(
            'messages.success.the_action_was_completed_successfully',
            200,
            $this->reports->outstanding($request->user()),
        );
    }

    public function source(ReportRangeRequest $request): JsonResponse
    {
        $data = $this->reports->revenueBySource($request->user(), $request->from(), $request->to());

        return $this->responseToJson('messages.success.the_action_was_completed_successfully', 200, $data);
    }

    public function daily(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        return $this->responseToJson(
            'messages.success.the_action_was_completed_successfully',
            200,
            $this->reports->daily($request->user(), $date),
        );
    }

    public function occupancy(ReportRangeRequest $request): JsonResponse
    {
        $data = $this->reports->occupancy($request->user(), $request->from(), $request->to());

        return $this->responseToJson('messages.success.the_action_was_completed_successfully', 200, $data);
    }

    public function channel(ReportRangeRequest $request): JsonResponse
    {
        $data = $this->reports->channel($request->user(), $request->from(), $request->to());

        return $this->responseToJson('messages.success.the_action_was_completed_successfully', 200, $data);
    }

    public function dashboard(Request $request): JsonResponse
    {
        return $this->responseToJson(
            'messages.success.the_action_was_completed_successfully',
            200,
            $this->reports->dashboardDue($request->user()),
        );
    }

    public function export(ReportRangeRequest $request, string $type): StreamedResponse|JsonResponse
    {
        $this->reports->assertCanExport($request->user());
        $format = $request->query('format', 'csv');

        $data = match ($type) {
            'revenue' => $this->reports->revenue($request->user(), $request->from(), $request->to()),
            'payments' => $this->reports->payments($request->user(), $request->from(), $request->to()),
            'outstanding' => $this->reports->outstanding($request->user()),
            default => null,
        };

        if ($data === null) {
            return $this->generalError('messages.folios.unknown_report', 422);
        }

        if ($format === 'pdf') {
            $html = $this->reports->toHtml($data, $type);

            return response()->streamDownload(function () use ($html) {
                echo $html;
            }, $type.'-report.html', [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        $csv = $this->reports->toCsv($data, $type);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $type.'-report.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
