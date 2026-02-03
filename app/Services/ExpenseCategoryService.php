<?php

namespace App\Services;

use App\Models\ExpenseCategory;
use App\Repositories\ExpenseCategoryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseCategoryService
{
    private ExpenseCategoryRepositoryInterface $categoryRepository;

    public function __construct(ExpenseCategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function getAllCategories(): Collection
    {
        return $this->categoryRepository->getAll();
    }

    public function getAllCategoriesPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return $this->categoryRepository->getAllPaginated($perPage);
    }

    public function getCategory(int $id): ?ExpenseCategory
    {
        return $this->categoryRepository->getById($id);
    }

    public function getMainCategories(): Collection
    {
        return $this->categoryRepository->getMainCategories();
    }

    public function getSubCategories(int $parentId): Collection
    {
        return $this->categoryRepository->getSubCategories($parentId);
    }

    public function getActiveCategories(): Collection
    {
        return $this->categoryRepository->getActiveCategories();
    }

    public function createCategory(array $data): ExpenseCategory
    {
        // Validate parent_id if provided
        if (isset($data['parent_id'])) {
            $parent = $this->categoryRepository->getById($data['parent_id']);
            if (!$parent) {
                throw new \Exception('Parent category not found');
            }
            // Ensure parent is a main category (no nested sub-categories beyond 2 levels)
            if ($parent->isSubCategory()) {
                throw new \Exception('Cannot create sub-category under another sub-category. Only main → sub structure is allowed.');
            }
        }

        return $this->categoryRepository->create($data);
    }

    public function updateCategory(int $id, array $data): bool
    {
        $category = $this->categoryRepository->getById($id);
        if (!$category) {
            return false;
        }

        // Validate parent_id if being updated
        if (isset($data['parent_id']) && $data['parent_id'] !== null) {
            // Prevent self-reference
            if ($data['parent_id'] == $id) {
                throw new \Exception('Category cannot be its own parent');
            }

            $parent = $this->categoryRepository->getById($data['parent_id']);
            if (!$parent) {
                throw new \Exception('Parent category not found');
            }

            // Ensure parent is a main category
            if ($parent->isSubCategory()) {
                throw new \Exception('Cannot set parent to a sub-category. Only main → sub structure is allowed.');
            }

            // If this category has children, it cannot become a sub-category
            if ($category->hasChildren()) {
                throw new \Exception('Cannot make a main category with sub-categories into a sub-category');
            }
        }

        return $this->categoryRepository->update($id, $data);
    }

    public function deleteCategory(int $id): bool
    {
        // The observer will handle validation (checking for expenses and children)
        return $this->categoryRepository->delete($id);
    }

    public function deactivateCategory(int $id): bool
    {
        return $this->categoryRepository->update($id, ['is_active' => false]);
    }

    public function activateCategory(int $id): bool
    {
        return $this->categoryRepository->update($id, ['is_active' => true]);
    }
}
