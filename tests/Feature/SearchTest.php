<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Business;
use App\Models\BusinessService;
use App\Models\Category;
use App\Models\JobListing;
use App\Models\Owner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_business_services_categories_and_job_details(): void
    {
        $area = Area::create(['name' => 'Qalqilya', 'slug' => 'qalqilya', 'status' => 'active']);
        $category = Category::create([
            'name' => 'Healthcare',
            'slug' => 'healthcare',
            'status' => 'active',
        ]);
        $business = $this->business($category, $area, ['name' => 'Local Center']);

        BusinessService::create([
            'business_id' => $business->id,
            'name' => 'Dental implants',
            'status' => 'active',
        ]);

        $job = $this->job($business, $area, [
            'title' => 'Clinic assistant',
            'requirements' => 'Dental experience is required',
        ]);

        $response = $this->get(route('search', ['q' => 'Dental']));

        $response->assertOk()
            ->assertViewHas('businesses', fn ($businesses) => $businesses->contains($business))
            ->assertViewHas('jobs', fn ($jobs) => $jobs->contains($job))
            ->assertViewHas('categories', fn ($categories) => $categories->firstWhere('id', $category->id)?->businesses_count === 1);

        $this->assertDatabaseHas('search_logs', [
            'keyword' => 'Dental',
            'type' => 'keyword',
            'results_count' => 2,
        ]);
    }

    public function test_area_filter_applies_to_businesses_and_jobs_and_expired_jobs_are_hidden(): void
    {
        $firstArea = Area::create(['name' => 'First', 'slug' => 'first', 'status' => 'active']);
        $secondArea = Area::create(['name' => 'Second', 'slug' => 'second', 'status' => 'active']);
        $category = Category::create(['name' => 'Services', 'slug' => 'services', 'status' => 'active']);
        $firstBusiness = $this->business($category, $firstArea, ['name' => 'First business']);
        $secondBusiness = $this->business($category, $secondArea, ['name' => 'Second business']);
        $availableJob = $this->job($firstBusiness, $firstArea, ['title' => 'Available job']);
        $this->job($firstBusiness, $firstArea, [
            'title' => 'Expired job',
            'expires_at' => now()->subDay(),
        ]);
        $this->job($secondBusiness, $secondArea, ['title' => 'Other area job']);

        $response = $this->get(route('search', ['area' => $firstArea->id]));

        $response->assertOk()
            ->assertViewHas('businesses', fn ($businesses) => $businesses->modelKeys() === [$firstBusiness->id])
            ->assertViewHas('jobs', fn ($jobs) => $jobs->modelKeys() === [$availableJob->id]);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->get(route('search', ['q' => Str::repeat('a', 101)]))
            ->assertSessionHasErrors('q');

        $this->get(route('search', ['area' => 999999]))
            ->assertSessionHasErrors('area');
    }

    private function business(Category $category, Area $area, array $attributes = []): Business
    {
        $owner = Owner::create([
            'name' => 'Owner '.Str::random(5),
            'email' => Str::random(8).'@example.test',
            'phone' => '059'.random_int(1000000, 9999999),
            'password' => 'password',
            'status' => 'active',
        ]);

        return Business::create(array_merge([
            'owner_id' => $owner->id,
            'category_id' => $category->id,
            'area_id' => $area->id,
            'name' => 'Business '.Str::random(5),
            'slug' => Str::uuid()->toString(),
            'status' => 'active',
        ], $attributes));
    }

    private function job(Business $business, Area $area, array $attributes = []): JobListing
    {
        return JobListing::create(array_merge([
            'business_id' => $business->id,
            'area_id' => $area->id,
            'title' => 'Job '.Str::random(5),
            'slug' => Str::uuid()->toString(),
            'description' => 'Job description',
            'employment_type' => 'full_time',
            'status' => 'active',
        ], $attributes));
    }
}
