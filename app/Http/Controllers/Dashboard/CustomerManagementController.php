<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerManagementController extends Controller
{
    /**
     * CUS-BE-004: the account statuses (customers.status), in display order, with the customer-specific label keys and
     * their English defaults (the same as the customers views). The filter and the validation use the values only.
     */
    private const STATUSES = [
        'active' => ['dashboard.Customers_Status_Active', 'Active'],
        'pending' => ['dashboard.Customers_Status_Pending', 'Pending activation'],
        'blocked' => ['dashboard.Customers_Status_Blocked', 'Blocked'],
    ];

    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'customers.view');

        // CUS-BE-002: search and status are read only as plain strings, as in the products list (PRD-19); an array
        // (search[]=x, status[foo]=active) or an unknown status is ignored and the list is shown unfiltered, never a 500.
        $text = fn (string $name): string => is_string($value = $request->query($name)) ? trim($value) : '';
        $search = $text('search');
        $status = array_key_exists($text('status'), self::STATUSES) ? $text('status') : null;

        $customers = Customer::query()
            ->withCount(['orders', 'addresses', 'reviews'])
            // CUS-BE-001: the name, email and phone alternatives are grouped, so they never bypass the status filter;
            // the LIKE wildcards typed by the admin are matched literally (escape character '!', MySQL and SQLite).
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';

                $query->where(fn ($match) => $match
                    ->whereRaw("customers.name like ? escape '!'", [$like])
                    ->orWhereRaw("customers.email like ? escape '!'", [$like])
                    ->orWhereRaw("customers.phone like ? escape '!'", [$like]));
            })
            ->when($status !== null, fn ($query) => $query->where('customers.status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.customers.index', [
            'customers' => $customers,
            'statuses' => $this->statuses(),
        ]);
    }

    public function show(Request $request, Customer $customer): View
    {
        $this->authorizeAbility($request, 'customers.view');

        $customer->load([
            'addresses.city',
            'orders' => fn ($query) => $query->latest()->limit(10),
            'reviews' => fn ($query) => $query->latest()->limit(10),
        ]);

        return view('dashboard.customers.show', [
            'customer' => $customer,
            'statuses' => $this->statuses(),
        ]);
    }

    public function updateStatus(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorizeAbility($request, 'customers.edit');

        // CUS-BE-003: the success and the validation errors both return to this customer's details page (named
        // route). back() and Laravel's default error redirect use url()->previous(), which prefers the Referer header
        // and can therefore point to another page or outside the application.
        $page = route('dashboard.customers.show', $customer);

        try {
            $data = $request->validate([
                'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            ], [], ['status' => $this->statusAttribute()]);
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($page);
        }

        $customer->update($data);

        return redirect()->to($page)->with('success', $this->untranslatedDefault('dashboard.Customer_status_updated', 'Customer status updated successfully.'));
    }

    /**
     * CUS-BE-004: status => label, from the customer-specific keys (AdminCustomerTranslationSeeder), never from the
     * shared dashboard.Active / Pending / Blocked keys, which other modules use with other meanings.
     *
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return array_map(fn (array $label): string => $this->untranslatedDefault(...$label), self::STATUSES);
    }

    /**
     * CUS-BE-006: the field name in the validation messages (lang/{locale}/validation.php supplies the sentences; this
     * replaces :attribute), in the admin's language: the label shown next to the field (dashboard.Customers_Status_Label,
     * AdminCustomerTranslationSeeder), with its first letter lowercased mid-sentence as in ProductManagementController.
     */
    private function statusAttribute(): string
    {
        $label = $this->untranslatedDefault('dashboard.Customers_Status_Label', 'New status');

        return preg_replace_callback('/^\p{Lu}(?=\p{Ll})/u', fn (array $match) => mb_strtolower($match[0]), $label);
    }

    /**
     * t() without auto-creating the key (as in ProductManagementController and CategoryManagementController): until
     * the customers translations are seeded, the English default is shown but no row is written, so an Arabic row is
     * never created with English text.
     */
    private function untranslatedDefault(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t($key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }
}
