<?php

namespace Tests\Feature\Dashboard;

use App\Models\Admin;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeHeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_home_hero_content(): void
    {
        $admin = Admin::factory()->create(['super_admin' => true]);
        $this->actingAs($admin, 'admin')->patchJson(route('dashboard.setting.home-content.update'), [
            'home_content' => ['hero_text_2' => 'عنوان تجريبي', 'hero_text_3' => 'عنوان فرعي تجريبي', 'hero_text_5' => 'وصف المقدمة'],
        ])->assertOk();
        $this->assertSame('عنوان تجريبي', Setting::query()->first()->home_content['hero_text_2']);
    }

    public function test_home_page_displays_saved_hero_content(): void
    {
        Setting::singleton()->update(['home_content' => ['hero_text_2' => 'محتوى ديناميكي',
            'hero_text_3' => 'من قاعدة البيانات', 'hero_text_5' => 'هذا النص محفوظ في إعدادات المقدمة.']]);
        $this->get(route('home'))->assertOk()->assertSeeText('محتوى ديناميكي')->assertSeeText('من قاعدة البيانات')
            ->assertSeeText('هذا النص محفوظ في إعدادات المقدمة.');
    }

    public function test_guest_cannot_manage_home_hero_content(): void
    {
        $this->get(route('dashboard.setting.home-content'))->assertRedirect(route('admin.login'));
    }
}
