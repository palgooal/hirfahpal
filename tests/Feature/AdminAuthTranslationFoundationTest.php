<?php

namespace Tests\Feature;

use App\Http\Middleware\DisableTranslationAutoCreate;
use App\Models\Language;
use App\Models\TranslationValue;
use Database\Seeders\AdminAuthTranslationSeeder;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminAuthTranslationFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const MISSING_KEY = 'dashboard.Foundation_Test_Missing_Key';

    protected function setUp(): void
    {
        parent::setUp();

        // Probe routes: one with the middleware, one without it.
        Route::middleware(['web', 'disableTranslationAutoCreate'])->get('/_test/translation-probe', fn () => $this->probe());
        Route::middleware('web')->get('/_test/translation-probe-open', fn () => $this->probe());
        Route::middleware(['web', 'disableTranslationAutoCreate'])->get('/_test/translation-probe-fails', function () {
            throw new \RuntimeException('boom');
        });
    }

    public function test_seeder_creates_missing_arabic_translations(): void
    {
        $this->seed(AdminAuthTranslationSeeder::class);

        foreach (AdminAuthTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['ar'], $this->value($key, 'ar'), $key);
        }
    }

    public function test_seeder_creates_missing_english_translations(): void
    {
        $this->seed(AdminAuthTranslationSeeder::class);

        foreach (AdminAuthTranslationSeeder::TRANSLATIONS as $key => $values) {
            $this->assertSame($values['en'], $this->value($key, 'en'), $key);
        }
    }

    public function test_running_the_seeder_twice_does_not_duplicate(): void
    {
        $expected = count(AdminAuthTranslationSeeder::TRANSLATIONS) * 2;

        $this->seed(AdminAuthTranslationSeeder::class);
        $this->assertSame($expected, TranslationValue::count());

        $this->seed(AdminAuthTranslationSeeder::class);
        $this->assertSame($expected, TranslationValue::count());
    }

    public function test_existing_values_are_never_overwritten(): void
    {
        $this->travelTo(now()->subDay());
        $editedArabic = TranslationValue::create(['key' => 'dashboard.Welcome_Back', 'locale' => 'ar', 'value' => 'أهلًا من جديد (معدّل)']);
        $editedEnglish = TranslationValue::create(['key' => 'dashboard.Password', 'locale' => 'en', 'value' => 'Pass phrase (edited)']);
        $autoCreated = TranslationValue::create(['key' => 'dashboard.Password', 'locale' => 'ar', 'value' => 'Password']);
        $this->travelBack();

        $snapshots = collect([$editedArabic, $editedEnglish, $autoCreated])->map(fn ($row) => $row->fresh()->getAttributes());

        $this->seed(AdminAuthTranslationSeeder::class);

        foreach ($snapshots as $attributes) {
            $this->assertSame($attributes, TranslationValue::find($attributes['id'])->getAttributes());
        }

        // Missing siblings are still created.
        $this->assertSame('Welcome back', $this->value('dashboard.Welcome_Back', 'en'));
    }

    public function test_unique_key_locale_constraint_is_respected(): void
    {
        $this->seed(AdminAuthTranslationSeeder::class);
        $this->seed(AdminAuthTranslationSeeder::class);

        $duplicates = TranslationValue::query()
            ->selectRaw('`key`, locale, count(*) as total')
            ->groupBy('key', 'locale')
            ->havingRaw('count(*) > 1')
            ->count();
        $this->assertSame(0, $duplicates);

        $this->expectException(UniqueConstraintViolationException::class);
        TranslationValue::create(['key' => 'dashboard.Sign_In', 'locale' => 'ar', 'value' => 'duplicate']);
    }

    public function test_cache_is_cleared_only_for_inserted_rows(): void
    {
        TranslationValue::create(['key' => 'dashboard.Sign_In', 'locale' => 'ar', 'value' => 'دخول (معدّل)']);
        cache()->put('translation.ar.dashboard.Sign_In', 'cached edited value', 60);
        cache()->put('translation.ar.dashboard.Welcome_Back', 'stale', 60);

        $this->seed(AdminAuthTranslationSeeder::class);

        $this->assertNull(cache()->get('translation.ar.dashboard.Welcome_Back'));
        $this->assertSame('cached edited value', cache()->get('translation.ar.dashboard.Sign_In'));

        app()->setLocale('ar');
        $this->assertSame('مرحبًا بعودتك', t('dashboard.Welcome_Back', 'Welcome back'));
    }

    public function test_middleware_disables_auto_create_during_the_request(): void
    {
        $this->assertTrue((bool) config('palgoals-locale.auto_create'));

        $this->get('/_test/translation-probe')
            ->assertOk()
            ->assertExactJson(['auto_create' => false, 'text' => 'Default text']);

        $this->assertSame(0, TranslationValue::where('key', self::MISSING_KEY)->count());
    }

    public function test_global_auto_create_behaviour_is_unchanged_outside_the_middleware(): void
    {
        $this->get('/_test/translation-probe')->assertOk();
        $this->assertTrue((bool) config('palgoals-locale.auto_create'));

        $this->get('/_test/translation-probe-open')
            ->assertOk()
            ->assertJson(['auto_create' => true]);

        $this->assertGreaterThan(0, TranslationValue::where('key', self::MISSING_KEY)->count());
    }

    public function test_configuration_is_restored_even_when_the_request_fails(): void
    {
        $this->withoutExceptionHandling();

        try {
            $this->get('/_test/translation-probe-fails');
            $this->fail('The probe route should throw.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertTrue((bool) config('palgoals-locale.auto_create'));
    }

    public function test_unseeded_active_language_falls_back_without_creating_rows(): void
    {
        Language::create(['name' => 'French', 'native' => 'Français', 'code' => 'fr', 'is_rtl' => false, 'is_active' => true]);
        $this->seed(AdminAuthTranslationSeeder::class);
        Route::middleware(['web', 'disableTranslationAutoCreate'])->get('/_test/translation-probe-fr', function () {
            app()->setLocale('fr');

            return response()->json(['text' => t('dashboard.Welcome_Back', 'Welcome back')]);
        });

        $this->get('/_test/translation-probe-fr')
            ->assertOk()
            ->assertExactJson(['text' => 'Welcome back']);

        $this->assertSame(0, TranslationValue::where('locale', 'fr')->count());
    }

    public function test_middleware_alias_is_registered_and_applied_only_to_admin_guest_auth_routes(): void
    {
        $aliases = app(HttpKernel::class)->getMiddlewareAliases();
        $this->assertSame(DisableTranslationAutoCreate::class, $aliases['disableTranslationAutoCreate'] ?? null);

        $applied = collect(Route::getRoutes()->getRoutes())
            ->reject(fn ($route) => str_starts_with($route->uri(), '_test/'))
            ->filter(fn ($route) => array_intersect(['disableTranslationAutoCreate', DisableTranslationAutoCreate::class], $route->gatherMiddleware()))
            ->map(fn ($route) => $route->getName())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'admin.login',
            'admin.login.store',
            'admin.password.email',
            'admin.password.request',
            'admin.password.reset',
            'admin.password.update',
        ], $applied);
    }

    private function probe()
    {
        return response()->json([
            'auto_create' => (bool) config('palgoals-locale.auto_create'),
            'text' => t(self::MISSING_KEY, 'Default text'),
        ]);
    }

    private function value(string $key, string $locale): ?string
    {
        return TranslationValue::where('key', $key)->where('locale', $locale)->value('value');
    }
}
