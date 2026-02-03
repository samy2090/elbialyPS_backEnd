<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Http\Requests\UploadExpenseAttachmentRequest;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    private ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    /**
     * Display all expenses
     */
    public function index(): JsonResponse
    {
        $perPage = request()->get('per_page', 15);
        $expenses = $this->expenseService->getAllExpenses($perPage);
        return response()->json($expenses);
    }

    /**
     * Display a specific expense
     */
    public function show(int $id): JsonResponse
    {
        $expense = $this->expenseService->getExpense($id);
        
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $expense]);
    }

    /**
     * Get expenses by date range
     */
    public function getByDateRange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = $validated['per_page'] ?? 15;
        $expenses = $this->expenseService->getExpensesByDateRange(
            $validated['start_date'],
            $validated['end_date'],
            $perPage
        );
        
        return response()->json($expenses);
    }

    /**
     * Get expenses by category
     */
    public function getByCategory(int $categoryId): JsonResponse
    {
        $perPage = request()->get('per_page', 15);
        $expenses = $this->expenseService->getExpensesByCategory($categoryId, $perPage);
        return response()->json($expenses);
    }

    /**
     * Get expenses by status
     */
    public function getByStatus(string $status): JsonResponse
    {
        if (!in_array($status, ['paid', 'unpaid'])) {
            return response()->json(['message' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $perPage = request()->get('per_page', 15);
        $expenses = $this->expenseService->getExpensesByStatus($status, $perPage);
        return response()->json($expenses);
    }

    /**
     * Get recurring expenses
     */
    public function getRecurring(): JsonResponse
    {
        $perPage = request()->get('per_page', 15);
        $expenses = $this->expenseService->getRecurringExpenses($perPage);
        return response()->json($expenses);
    }

    /**
     * Store a new expense
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        try {
            $expense = $this->expenseService->createExpense($request->validated());
            return response()->json([
                'message' => 'Expense created successfully',
                'data' => $expense
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update an expense
     */
    public function update(UpdateExpenseRequest $request, int $id): JsonResponse
    {
        try {
            $success = $this->expenseService->updateExpense($id, $request->validated());
            
            if (!$success) {
                return response()->json(['message' => 'Expense not found'], Response::HTTP_NOT_FOUND);
            }

            $expense = $this->expenseService->getExpense($id);
            
            return response()->json([
                'message' => 'Expense updated successfully',
                'data' => $expense
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete an expense
     */
    public function destroy(int $id): JsonResponse
    {
        $success = $this->expenseService->deleteExpense($id);
        
        if (!$success) {
            return response()->json(['message' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Expense deleted successfully']);
    }

    /**
     * Mark expense as paid
     */
    public function markAsPaid(int $id): JsonResponse
    {
        $success = $this->expenseService->markAsPaid($id);
        
        if (!$success) {
            return response()->json(['message' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Expense marked as paid']);
    }

    /**
     * Mark expense as unpaid
     */
    public function markAsUnpaid(int $id): JsonResponse
    {
        $success = $this->expenseService->markAsUnpaid($id);
        
        if (!$success) {
            return response()->json(['message' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Expense marked as unpaid']);
    }

    /**
     * Upload attachment for an expense
     */
    public function uploadAttachment(UploadExpenseAttachmentRequest $request, int $id): JsonResponse
    {
        try {
            $attachment = $this->expenseService->uploadAttachment($id, $request->file('file'));
            
            return response()->json([
                'message' => 'Attachment uploaded successfully',
                'data' => $attachment
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get attachments for an expense
     */
    public function getAttachments(int $id): JsonResponse
    {
        $expense = $this->expenseService->getExpense($id);
        
        if (!$expense) {
            return response()->json(['message' => 'Expense not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $expense->attachments]);
    }

    /**
     * Delete an attachment
     */
    public function deleteAttachment(int $id, int $attachmentId): JsonResponse
    {
        try {
            $success = $this->expenseService->deleteAttachment($id, $attachmentId);
            
            if (!$success) {
                return response()->json(['message' => 'Attachment not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json(['message' => 'Attachment deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
