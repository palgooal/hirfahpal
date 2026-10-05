<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ReturnRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReturnRequestManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'return-requests.view');

        $returnRequests = ReturnRequest::query()
            ->with(['order', 'vendorOrder', 'customer'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.return-requests.index', [
            'returnRequests' => $returnRequests,
            'statuses' => ['requested', 'under_review', 'approved', 'rejected', 'closed'],
        ]);
    }

    public function show(Request $request, ReturnRequest $returnRequest): View
    {
        $this->authorizeAbility($request, 'return-requests.view');

        $returnRequest->load(['order', 'vendorOrder.vendor.profile', 'customer']);

        return view('dashboard.return-requests.show', compact('returnRequest'));
    }

    public function review(Request $request, ReturnRequest $returnRequest): RedirectResponse
    {
        $this->authorizeAbility($request, 'return-requests.edit');

        $data = $request->validate([
            'status' => ['required', Rule::in(['requested', 'under_review', 'approved', 'rejected', 'closed'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $returnRequest->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? $returnRequest->admin_note,
            'reviewed_by' => in_array($data['status'], ['approved', 'rejected', 'closed'], true) ? $request->user('admin')->id : null,
            'reviewed_at' => in_array($data['status'], ['approved', 'rejected', 'closed'], true) ? now() : null,
        ]);

        return back()->with('success', t('dashboard.Return_request_updated', 'Return request updated successfully.'));
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }
}
