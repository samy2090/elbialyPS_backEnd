<?php

namespace App\Http\Controllers;

use App\Http\Requests\FinancialAnalyticsRequest;
use App\Services\FinancialAnalyticsService;
use Illuminate\Http\JsonResponse;

class FinancialAnalyticsController extends Controller
{
    private FinancialAnalyticsService $analytics;

    public function __construct(FinancialAnalyticsService $analytics)
    {
        $this->analytics = $analytics;
    }

    public function revenues(FinancialAnalyticsRequest $request): JsonResponse
    {
        $data = $this->analytics->getRevenues(
            $request->period(),
            $request->input('from'),
            $request->input('to'),
        );

        return response()->json(['data' => $data]);
    }

    public function profits(FinancialAnalyticsRequest $request): JsonResponse
    {
        $data = $this->analytics->getProfits(
            $request->period(),
            $request->input('from'),
            $request->input('to'),
            $request->mode(),
        );

        return response()->json(['data' => $data]);
    }

    public function summary(FinancialAnalyticsRequest $request): JsonResponse
    {
        $data = $this->analytics->getSummary(
            $request->input('from'),
            $request->input('to'),
        );

        return response()->json(['data' => $data]);
    }

    public function breakdown(FinancialAnalyticsRequest $request): JsonResponse
    {
        $data = $this->analytics->getBreakdown(
            $request->input('from'),
            $request->input('to'),
        );

        return response()->json(['data' => $data]);
    }
}
