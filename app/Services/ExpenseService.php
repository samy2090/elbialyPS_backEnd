<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Repositories\ExpenseRepositoryInterface;
use App\Repositories\ExpenseCategoryRepositoryInterface;
use App\Repositories\ExpenseRecurrenceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    private ExpenseRepositoryInterface $expenseRepository;
    private ExpenseCategoryRepositoryInterface $categoryRepository;
    private ExpenseRecurrenceRepositoryInterface $recurrenceRepository;

    public function __construct(
        ExpenseRepositoryInterface $expenseRepository,
        ExpenseCategoryRepositoryInterface $categoryRepository,
        ExpenseRecurrenceRepositoryInterface $recurrenceRepository
    ) {
        $this->expenseRepository = $expenseRepository;
        $this->categoryRepository = $categoryRepository;
        $this->recurrenceRepository = $recurrenceRepository;
    }

    public function getAllExpenses(int $perPage = 15): LengthAwarePaginator
    {
        return $this->expenseRepository->getAll($perPage);
    }

    public function getExpense(int $id): ?Expense
    {
        return $this->expenseRepository->getById($id);
    }

    public function getExpensesByDateRange(string $startDate, string $endDate, int $perPage = 15): LengthAwarePaginator
    {
        return $this->expenseRepository->getByDateRange($startDate, $endDate, $perPage);
    }

    public function getExpensesByCategory(int $categoryId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->expenseRepository->getByCategory($categoryId, $perPage);
    }

    public function getExpensesByStatus(string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->expenseRepository->getByStatus($status, $perPage);
    }

    public function getRecurringExpenses(int $perPage = 15): LengthAwarePaginator
    {
        return $this->expenseRepository->getRecurringExpenses($perPage);
    }

    public function createExpense(array $data): Expense
    {
        // Validate category exists
        $category = $this->categoryRepository->getById($data['category_id']);
        if (!$category) {
            throw new \Exception('Category not found');
        }

        // Product expense: category must be "Products" and product must exist
        if (isset($data['product_id']) && isset($data['quantity'])) {
            $productsCategoryId = ExpenseCategory::getProductsCategoryId();
            if (!$productsCategoryId || (int) $data['category_id'] !== $productsCategoryId) {
                throw new \Exception('Product expenses must use the Products category.');
            }
            $product = Product::find($data['product_id']);
            if (!$product) {
                throw new \Exception('Product not found');
            }
        }

        // Validate recurrence if provided
        if (isset($data['recurring_id'])) {
            $recurrence = $this->recurrenceRepository->getById($data['recurring_id']);
            if (!$recurrence) {
                throw new \Exception('Recurrence not found');
            }
            $data['is_recurring'] = true;
        }

        $expense = $this->expenseRepository->create($data);

        // Product expense: increase product stock
        if ($expense->isProductExpense()) {
            Product::where('id', $expense->product_id)->increment('stock', $expense->quantity);
        }

        return $expense;
    }

    public function updateExpense(int $id, array $data): bool
    {
        $expense = $this->expenseRepository->getById($id);
        if (!$expense) {
            return false;
        }

        // Validate category if being updated
        if (isset($data['category_id'])) {
            $category = $this->categoryRepository->getById($data['category_id']);
            if (!$category) {
                throw new \Exception('Category not found');
            }
        }

        // Product expense: when product_id is set, category must be Products
        if (isset($data['product_id'])) {
            $productsCategoryId = ExpenseCategory::getProductsCategoryId();
            if (!$productsCategoryId || (int) ($data['category_id'] ?? $expense->category_id) !== $productsCategoryId) {
                throw new \Exception('Product expenses must use the Products category.');
            }
        }

        // Validate recurrence if being updated
        if (isset($data['recurring_id'])) {
            $recurrence = $this->recurrenceRepository->getById($data['recurring_id']);
            if (!$recurrence) {
                throw new \Exception('Recurrence not found');
            }
            $data['is_recurring'] = true;
        }

        // Product expense stock: new_stock = old_stock - old_quantity + new_quantity
        if ($expense->isProductExpense()) {
            $oldQuantity = (int) $expense->quantity;
            $newQuantity = isset($data['quantity']) ? (int) $data['quantity'] : $oldQuantity;
            $productId = $data['product_id'] ?? $expense->product_id;

            if ($productId && ($oldQuantity !== $newQuantity || (isset($data['product_id']) && (int) $data['product_id'] !== (int) $expense->product_id))) {
                $product = Product::find($productId);
                if (!$product) {
                    throw new \Exception('Product not found');
                }
                // Same product: adjust stock by delta
                if ((int) $productId === (int) $expense->product_id) {
                    $product->decrement('stock', $oldQuantity);
                    $product->increment('stock', $newQuantity);
                } else {
                    // Changed to different product: reverse old, add new
                    Product::where('id', $expense->product_id)->decrement('stock', $oldQuantity);
                    $product->increment('stock', $newQuantity);
                }
            }
        }

        return $this->expenseRepository->update($id, $data);
    }

    public function deleteExpense(int $id): bool
    {
        return $this->expenseRepository->delete($id);
    }

    public function markAsPaid(int $id): bool
    {
        return $this->expenseRepository->update($id, [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function markAsUnpaid(int $id): bool
    {
        return $this->expenseRepository->update($id, [
            'status' => 'unpaid',
            'paid_at' => null,
        ]);
    }

    /**
     * Create expense from recurring expense (confirm payment).
     * expense_date = actual payment day (today). Updates recurrence: last_payment_date, last_reminded_at, next_payment_date.
     */
    public function createExpenseFromRecurrence(int $recurrenceId): Expense
    {
        $recurrence = $this->recurrenceRepository->getById($recurrenceId);
        if (!$recurrence) {
            throw new \Exception('Recurrence not found');
        }

        if (!$recurrence->is_active) {
            throw new \Exception('Recurrence is not active');
        }

        $paymentDate = now()->format('Y-m-d');

        // Create expense with actual payment date (history accurate)
        $expenseData = [
            'title' => $recurrence->title,
            'price' => $recurrence->price,
            'expense_date' => $paymentDate,
            'category_id' => $recurrence->category_id,
            'is_recurring' => true,
            'recurring_id' => $recurrence->id,
            'status' => 'paid',
            'paid_at' => now(),
        ];

        $expense = $this->expenseRepository->create($expenseData);

        // Current due we're fulfilling: stored next_payment_date or first due date
        $currentDue = $recurrence->next_payment_date
            ? \Carbon\Carbon::parse($recurrence->next_payment_date)->format('Y-m-d')
            : null;

        if ($currentDue === null) {
            $firstDue = \App\Models\ExpenseRecurrence::computeFirstDueDate(
                $recurrence->start_date->format('Y-m-d'),
                (int) $recurrence->due_day,
                $recurrence->frequency,
                $recurrence->end_date?->format('Y-m-d')
            );
            $currentDue = $firstDue ? $firstDue->format('Y-m-d') : null;
        }

        // Next due = one period after current due
        $nextDue = null;
        if ($currentDue) {
            $nextDueCarbon = \App\Models\ExpenseRecurrence::computeNextDueDate(
                $currentDue,
                (int) $recurrence->due_day,
                $recurrence->frequency,
                $recurrence->end_date?->format('Y-m-d')
            );
            $nextDue = $nextDueCarbon ? $nextDueCarbon->format('Y-m-d') : null;
        }

        $this->recurrenceRepository->update($recurrence->id, [
            'last_payment_date' => $paymentDate,
            'last_reminded_at' => now(),
            'next_payment_date' => $nextDue,
        ]);

        return $expense;
    }

    /**
     * Upload attachment for an expense
     */
    public function uploadAttachment(int $expenseId, $file): array
    {
        $expense = $this->expenseRepository->getById($expenseId);
        if (!$expense) {
            throw new \Exception('Expense not found');
        }

        // Validate file
        $allowedTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'txt', 'csv'];
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (!in_array($extension, $allowedTypes)) {
            throw new \Exception('File type not allowed. Allowed types: ' . implode(', ', $allowedTypes));
        }

        // Validate file size (50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB in bytes
        if ($file->getSize() > $maxSize) {
            throw new \Exception('File size exceeds 50MB limit');
        }

        // Store file
        $path = $file->store('expense_attachments', 'public');

        // Create attachment record
        $attachment = $expense->attachments()->create([
            'file_path' => $path,
            'file_type' => $extension,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
        ]);

        return [
            'id' => $attachment->id,
            'file_path' => $path,
            'file_url' => $attachment->file_url,
            'file_type' => $extension,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $attachment->file_size,
            'file_size_human' => $attachment->file_size_human,
        ];
    }

    /**
     * Delete attachment
     */
    public function deleteAttachment(int $expenseId, int $attachmentId): bool
    {
        $expense = $this->expenseRepository->getById($expenseId);
        if (!$expense) {
            throw new \Exception('Expense not found');
        }

        $attachment = $expense->attachments()->find($attachmentId);
        if (!$attachment) {
            throw new \Exception('Attachment not found');
        }

        return $attachment->delete();
    }
}
