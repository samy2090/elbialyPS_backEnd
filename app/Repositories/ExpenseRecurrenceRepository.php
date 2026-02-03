<?php

namespace App\Repositories;

use App\Models\ExpenseRecurrence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseRecurrenceRepository implements ExpenseRecurrenceRepositoryInterface
{
    public function getAll(int $perPage = 15): LengthAwarePaginator
    {
        return ExpenseRecurrence::with(['category', 'expenses'])
            ->orderBy('start_date', 'desc')
            ->paginate($perPage);
    }

    public function getById(int $id): ?ExpenseRecurrence
    {
        return ExpenseRecurrence::with(['category', 'expenses'])
            ->find($id);
    }

    public function getActive(): Collection
    {
        return ExpenseRecurrence::active()
            ->with('category')
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getOverdue(): Collection
    {
        return ExpenseRecurrence::overdue()
            ->with('category')
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function getDueWithin(int $days): Collection
    {
        return ExpenseRecurrence::dueWithin($days)
            ->with('category')
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function create(array $data): ExpenseRecurrence
    {
        return ExpenseRecurrence::create($data);
    }

    public function update(int $id, array $data): bool
    {
        return ExpenseRecurrence::where('id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        return ExpenseRecurrence::destroy($id) > 0;
    }
}
