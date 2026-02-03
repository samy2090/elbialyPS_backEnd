<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseRecurrence;
use App\Repositories\ExpenseRecurrenceRepositoryInterface;
use App\Repositories\ExpenseCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseRecurrenceService
{
    private ExpenseRecurrenceRepositoryInterface $recurrenceRepository;
    private ExpenseCategoryRepositoryInterface $categoryRepository;

    public function __construct(
        ExpenseRecurrenceRepositoryInterface $recurrenceRepository,
        ExpenseCategoryRepositoryInterface $categoryRepository
    ) {
        $this->recurrenceRepository = $recurrenceRepository;
        $this->categoryRepository = $categoryRepository;
    }

    public function getAllRecurrences(int $perPage = 15): LengthAwarePaginator
    {
        return $this->recurrenceRepository->getAll($perPage);
    }

    public function getRecurrence(int $id): ?ExpenseRecurrence
    {
        return $this->recurrenceRepository->getById($id);
    }

    public function getActiveRecurrences(): Collection
    {
        return $this->recurrenceRepository->getActive();
    }

    public function getOverdueRecurrences(): Collection
    {
        return $this->recurrenceRepository->getOverdue();
    }

    public function getDueWithin(int $days = 30): Collection
    {
        return $this->recurrenceRepository->getDueWithin($days);
    }

    public function createRecurrence(array $data): ExpenseRecurrence
    {
        // Validate category exists
        $category = $this->categoryRepository->getById($data['category_id']);
        if (!$category) {
            throw new \Exception('Category not found');
        }

        // Validate due_day
        if ($data['due_day'] < 1 || $data['due_day'] > 31) {
            throw new \Exception('Due day must be between 1 and 31');
        }

        // Validate frequency
        if (!in_array($data['frequency'], ['monthly', 'yearly'])) {
            throw new \Exception('Frequency must be monthly or yearly');
        }

        // Validate dates
        if (isset($data['end_date']) && $data['end_date'] < $data['start_date']) {
            throw new \Exception('End date must be after start date');
        }

        // Set initial next_payment_date: first due date on or after start_date with day = due_day
        $firstDue = ExpenseRecurrence::computeFirstDueDate(
            $data['start_date'],
            (int) $data['due_day'],
            $data['frequency'],
            $data['end_date'] ?? null
        );
        $data['next_payment_date'] = $firstDue ? $firstDue->format('Y-m-d') : null;

        return $this->recurrenceRepository->create($data);
    }

    public function updateRecurrence(int $id, array $data): bool
    {
        $recurrence = $this->recurrenceRepository->getById($id);
        if (!$recurrence) {
            return false;
        }

        // Validate category if being updated
        if (isset($data['category_id'])) {
            $category = $this->categoryRepository->getById($data['category_id']);
            if (!$category) {
                throw new \Exception('Category not found');
            }
        }

        // Validate due_day if being updated
        if (isset($data['due_day']) && ($data['due_day'] < 1 || $data['due_day'] > 31)) {
            throw new \Exception('Due day must be between 1 and 31');
        }

        // Validate frequency if being updated
        if (isset($data['frequency']) && !in_array($data['frequency'], ['monthly', 'yearly'])) {
            throw new \Exception('Frequency must be monthly or yearly');
        }

        // Validate dates if being updated
        $startDate = $data['start_date'] ?? $recurrence->start_date;
        $endDate = $data['end_date'] ?? $recurrence->end_date;
        if ($endDate && $endDate < $startDate) {
            throw new \Exception('End date must be after start date');
        }

        $updated = $this->recurrenceRepository->update($id, $data);

        // When price is updated, sync all linked expenses to the new price
        if ($updated && isset($data['price'])) {
            Expense::where('recurring_id', $id)->update(['price' => $data['price']]);
        }

        return $updated;
    }

    public function deleteRecurrence(int $id): bool
    {
        return $this->recurrenceRepository->delete($id);
    }

    public function deactivateRecurrence(int $id): bool
    {
        return $this->recurrenceRepository->update($id, ['is_active' => false]);
    }

    public function activateRecurrence(int $id): bool
    {
        return $this->recurrenceRepository->update($id, ['is_active' => true]);
    }
}
