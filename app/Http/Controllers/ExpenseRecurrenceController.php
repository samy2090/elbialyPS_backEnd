<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRecurrenceRequest;
use App\Http\Requests\UpdateExpenseRecurrenceRequest;
use App\Services\ExpenseRecurrenceService;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ExpenseRecurrenceController extends Controller
{
    private ExpenseRecurrenceService $recurrenceService;
    private ExpenseService $expenseService;

    public function __construct(
        ExpenseRecurrenceService $recurrenceService,
        ExpenseService $expenseService
    ) {
        $this->recurrenceService = $recurrenceService;
        $this->expenseService = $expenseService;
    }

    /**
     * Display all recurrences
     */
    public function index(): JsonResponse
    {
        $perPage = request()->get('per_page', 15);
        $recurrences = $this->recurrenceService->getAllRecurrences($perPage);
        return response()->json($recurrences);
    }

    /**
     * Display a specific recurrence
     */
    public function show(int $id): JsonResponse
    {
        $recurrence = $this->recurrenceService->getRecurrence($id);
        
        if (!$recurrence) {
            return response()->json(['message' => 'Recurrence not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $recurrence]);
    }

    /**
     * Get active recurrences
     */
    public function getActive(): JsonResponse
    {
        $recurrences = $this->recurrenceService->getActiveRecurrences();
        return response()->json(['data' => $recurrences]);
    }

    /**
     * Get overdue recurrences
     */
    public function getOverdue(): JsonResponse
    {
        $recurrences = $this->recurrenceService->getOverdueRecurrences();
        return response()->json(['data' => $recurrences]);
    }

    /**
     * Get recurrences due within X days
     */
    public function getDueWithin(): JsonResponse
    {
        $days = request()->get('days', 30);
        $recurrences = $this->recurrenceService->getDueWithin($days);
        return response()->json(['data' => $recurrences]);
    }

    /**
     * Store a new recurrence
     */
    public function store(StoreExpenseRecurrenceRequest $request): JsonResponse
    {
        try {
            $recurrence = $this->recurrenceService->createRecurrence($request->validated());
            return response()->json([
                'message' => 'Recurrence created successfully',
                'data' => $recurrence
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update a recurrence
     */
    public function update(UpdateExpenseRecurrenceRequest $request, int $id): JsonResponse
    {
        try {
            $success = $this->recurrenceService->updateRecurrence($id, $request->validated());
            
            if (!$success) {
                return response()->json(['message' => 'Recurrence not found'], Response::HTTP_NOT_FOUND);
            }

            $recurrence = $this->recurrenceService->getRecurrence($id);
            
            return response()->json([
                'message' => 'Recurrence updated successfully',
                'data' => $recurrence
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a recurrence
     */
    public function destroy(int $id): JsonResponse
    {
        $success = $this->recurrenceService->deleteRecurrence($id);
        
        if (!$success) {
            return response()->json(['message' => 'Recurrence not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Recurrence deleted successfully']);
    }

    /**
     * Deactivate a recurrence
     */
    public function deactivate(int $id): JsonResponse
    {
        $success = $this->recurrenceService->deactivateRecurrence($id);
        
        if (!$success) {
            return response()->json(['message' => 'Recurrence not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Recurrence deactivated successfully']);
    }

    /**
     * Activate a recurrence
     */
    public function activate(int $id): JsonResponse
    {
        $success = $this->recurrenceService->activateRecurrence($id);
        
        if (!$success) {
            return response()->json(['message' => 'Recurrence not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Recurrence activated successfully']);
    }

    /**
     * Confirm payment for a recurring expense.
     * Creates expense (expense_date = today), updates recurrence: last_payment_date, last_reminded_at, next_payment_date.
     */
    public function confirmPayment(int $id): JsonResponse
    {
        try {
            $expense = $this->expenseService->createExpenseFromRecurrence($id);
            
            return response()->json([
                'message' => 'Recurring expense payment confirmed and expense created successfully',
                'data' => $expense
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
