<?php

namespace App\Repositories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ExpenseRepositoryInterface
{
    public function getAll(int $perPage = 15): LengthAwarePaginator;
    public function getById(int $id): ?Expense;
    public function getByDateRange(string $startDate, string $endDate, int $perPage = 15): LengthAwarePaginator;
    public function getByCategory(int $categoryId, int $perPage = 15): LengthAwarePaginator;
    public function getByStatus(string $status, int $perPage = 15): LengthAwarePaginator;
    public function getRecurringExpenses(int $perPage = 15): LengthAwarePaginator;
    public function create(array $data): Expense;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
