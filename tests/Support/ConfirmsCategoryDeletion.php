<?php

namespace Tests\Support;

use App\Models\Admin;
use App\Models\Category;
use App\Support\Categories\CategoryDeleteConfirmation;
use Illuminate\Testing\TestResponse;

/**
 * N-CAT-1: a category DELETE needs a confirmation the server issued. Tests that are about what happens after
 * the confirmation (protection, messages, media) get one through the real CategoryDeleteConfirmation.
 */
trait ConfirmsCategoryDeletion
{
    protected function issueDeleteConfirmation(Admin $admin, Category|int $category): string
    {
        $this->startSession();

        return (new CategoryDeleteConfirmation(app('session')->driver()))
            ->issue($category instanceof Category ? $category->id : $category, $admin->id);
    }

    protected function confirmedDelete(Admin $admin, Category $category, ?string $from = null): TestResponse
    {
        $token = $this->issueDeleteConfirmation($admin, $category);

        return $this->actingAs($admin, 'admin')
            ->from($from ?? route('dashboard.categories.index'))
            ->delete(route('dashboard.categories.destroy', $category), ['confirmation' => $token]);
    }
}
