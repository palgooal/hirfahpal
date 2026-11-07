<?php

namespace Tests;

use App\Models\Category;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * VPM-CATEGORY-REQUIRED-01: an active category for a vendor product's create or update payload (the vendor API
     * requires one). Created once per test database and reused.
     */
    protected function activeCategoryId(): int
    {
        return Category::query()->firstOrCreate(['slug' => 'test-active-category'], ['name' => 'Test category', 'is_active' => true])->id;
    }
}
