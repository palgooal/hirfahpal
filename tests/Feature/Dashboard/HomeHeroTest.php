<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\HomeHero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_home_hero_content(): void
    {
        $admin = Admin::create([
            'name' => 'Admin',
            'email' => 'hero-admin@example.com',
            'phone' => '0599000010',
            'password' => 'password',
            'status' => 'active',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->put(route('dashboard.home-hero.update'), [
                'title' => 'عنوان تجريبي',
                'sub_title' => 'عنوان فرعي تجريبي',
                'description' => 'وصف تجريبي لمقدمة الصفحة الرئيسية.',
            ]);

        $response->assertRedirect(route('dashboard.home-hero.edit'));
        $this->assertDatabaseHas('home_heroes', [
            'title' => 'عنوان تجريبي',
            'sub_title' => 'عنوان فرعي تجريبي',
            'description' => 'وصف تجريبي لمقدمة الصفحة الرئيسية.',
        ]);
    }

    public function test_home_page_displays_saved_hero_content(): void
    {
        HomeHero::query()->firstOrFail()->update([
            'title' => 'محتوى ديناميكي',
            'sub_title' => 'من قاعدة البيانات',
            'description' => 'هذا النص محفوظ في جدول مقدمة الصفحة.',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('محتوى ديناميكي')
            ->assertSeeText('من قاعدة البيانات')
            ->assertSeeText('هذا النص محفوظ في جدول مقدمة الصفحة.');
    }

    public function test_guest_cannot_manage_home_hero_content(): void
    {
        $this->get(route('dashboard.home-hero.edit'))
            ->assertRedirect(route('admin.login'));
    }
}
