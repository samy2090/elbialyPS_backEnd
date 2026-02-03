<?php

namespace App\Http\Controllers;

use App\Services\ExpenseReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExpenseReportController extends Controller
{
    private ExpenseReportService $reportService;

    public function __construct(ExpenseReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Get summary report
     */
    public function getSummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $summary = $this->reportService->getSummary(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json(['data' => $summary]);
    }

    /**
     * Get expenses by category report
     */
    public function getByCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $report = $this->reportService->getByCategory(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json(['data' => $report]);
    }

    /**
     * Get paid vs unpaid report
     */
    public function getPaidVsUnpaid(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date|date_format:Y-m-d',
            'end_date' => 'nullable|date|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        $report = $this->reportService->getPaidVsUnpaid(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json(['data' => $report]);
    }

    /**
     * Get monthly summary for a year
     */
    public function getMonthlySummary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $report = $this->reportService->getMonthlySummary($validated['year']);

        return response()->json(['data' => $report]);
    }

    /**
     * Get upcoming recurring expenses
     */
    public function getUpcomingRecurring(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
        ]);

        $report = $this->reportService->getUpcomingRecurring($validated['days'] ?? 30);

        return response()->json(['data' => $report]);
    }

    /**
     * Get overdue recurring expenses
     */
    public function getOverdueRecurring(): JsonResponse
    {
        $report = $this->reportService->getOverdueRecurring();

        return response()->json(['data' => $report]);
    }
}
