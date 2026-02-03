<?php

namespace App\Repositories;

use App\Models\ExpenseRecurrence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ExpenseRecurrenceRepositoryInterface
{
    public function getAll(int $perPage = 15): LengthAwarePaginator;
    public function getById(int $id): ?ExpenseRecurrence;
    public function getActive(): Collection;
    public function getOverdue(): Collection;
    public function getDueWithin(int $days): Collection;
    public function create(array $data): ExpenseRecurrence;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
