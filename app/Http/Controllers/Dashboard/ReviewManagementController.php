<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReviewManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'reviews.view');

        $reviews = Review::query()
            ->with(['customer'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('reviewable_type', $request->input('type')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.reviews.index', [
            'reviews' => $reviews,
            'statuses' => $this->statuses(),
            'types' => ['product', 'vendor', 'delivery_driver'],
        ]);
    }

    public function updateStatus(Request $request, Review $review): RedirectResponse
    {
        $this->authorizeAbility($request, 'reviews.edit');

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
        ]);

        $review->update($data);

        return back()->with('success', t('dashboard.Review_status_updated', 'Review status updated successfully.'));
    }

    public function destroy(Request $request, Review $review): RedirectResponse
    {
        $this->authorizeAbility($request, 'reviews.delete');

        $review->delete();

        return back()->with('success', t('dashboard.Review_deleted', 'Review deleted successfully.'));
    }

    private function statuses(): array
    {
        return [
            'pending' => t('dashboard.Pending', 'Pending'),
            'published' => t('dashboard.Published', 'Published'),
            'hidden' => t('dashboard.Hidden', 'Hidden'),
        ];
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }
}
