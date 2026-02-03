<?php

namespace App\Repositories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ExpenseRepository implements ExpenseRepositoryInterface
{
    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return Expense::with(['category', 'recurrence', 'product', 'attachments', 'creator', 'updater'])
            ->orderBy('expense_date', 'desc')
            ->paginate($perPage);
    }

    public function getById(int $id): ?Expense
    {
        return Expense::with(['category', 'recurrence', 'product', 'attachments', 'creator', 'updater'])
            ->find($id);
    }

    public function getByDateRange(string $startDate, string $endDate, int $perPage = 15): LengthAwarePaginator
    {
        return Expense::with(['category', 'recurrence', 'product', 'attachments'])
            ->dateRange($startDate, $endDate)
            ->orderBy('expense_date', 'desc')
            ->paginate($perPage);
    }

    public function getByCategory(int $categoryId, int $perPage = 15): LengthAwarePaginator
    {
        return Expense::with(['category', 'recurrence', 'product', 'attachments'])
            ->byCategory($categoryId)
            ->orderBy('expense_date', 'desc')
            ->paginate($perPage);
    }

    public function getByStatus(string $status, int $perPage = 15): LengthAwarePaginator
    {
        return Expense::with(['category', 'recurrence', 'product', 'attachments'])
            ->where('status', $status)
            ->orderBy('expense_date', 'desc')
            ->paginate($perPage);
    }

    public function getRecurringExpenses(int $perPage = 15): LengthAwarePaginator
    {
        return Expense::with(['category', 'recurrence', 'product', 'attachments'])
            ->recurring()
            ->orderBy('expense_date', 'desc')
            ->paginate($perPage);
    }

    public function create(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            return Expense::create($data);
        });
    }

    public function update(int $id, array $data): bool
    {
        return Expense::where('id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        return Expense::destroy($id) > 0;
    }
}
