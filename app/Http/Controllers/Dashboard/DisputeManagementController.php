<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DisputeManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'disputes.view');

        $disputes = Dispute::query()
            ->with(['order', 'vendorOrder', 'customer'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->input('type')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.disputes.index', [
            'disputes' => $disputes,
            'statuses' => ['open', 'under_review', 'resolved', 'closed'],
            'types' => ['order', 'delivery', 'payment', 'product', 'other'],
        ]);
    }

    public function show(Request $request, Dispute $dispute): View
    {
        $this->authorizeAbility($request, 'disputes.view');

        $dispute->load(['order', 'vendorOrder.vendor.profile', 'customer']);

        return view('dashboard.disputes.show', compact('dispute'));
    }

    public function resolve(Request $request, Dispute $dispute): RedirectResponse
    {
        $this->authorizeAbility($request, 'disputes.edit');

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'under_review', 'resolved', 'closed'])],
            'resolution' => ['nullable', 'required_if:status,resolved,closed', 'string', 'max:2000'],
        ]);

        $dispute->update([
            'status' => $data['status'],
            'resolution' => $data['resolution'] ?? $dispute->resolution,
            'resolved_by' => in_array($data['status'], ['resolved', 'closed'], true) ? $request->user('admin')->id : null,
            'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? now() : null,
        ]);

        return back()->with('success', t('dashboard.Dispute_updated', 'Dispute updated successfully.'));
    }

    private function authorizeAbility(Request $request, string $ability): void
    {
        $admin = $request->user('admin');

        abort_unless($admin?->isSuperAdmin() || $admin?->hasAbility($ability), 403);
    }
}
