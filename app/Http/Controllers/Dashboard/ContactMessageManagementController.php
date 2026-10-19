<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CONTACT-ADMIN-IMPLEMENTATION-01: the storefront contact messages in the admin dashboard.
 * Access needs contact-messages.view (super admins always pass, DEC-CONTACT-ADMIN-02). The details page marks the
 * message read the first time an authorized admin opens it and keeps that date afterwards (DEC-CONTACT-ADMIN-01).
 * CONTACT-ADMIN-DELETE-02: a super admin may soft-delete a message (destroy). The message text is never logged.
 */
class ContactMessageManagementController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'contact-messages.view');

        $messages = ContactMessage::query()
            ->select(['id', 'customer_id', 'name', 'email', 'subject', 'read_at', 'created_at'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.contact-messages.index', ['messages' => $messages]);
    }

    /**
     * The id is a plain number (no model binding), so the ability is checked before the message is read: an admin
     * without it gets 403 whether or not the message exists, and read_at is never touched for them.
     */
    public function show(Request $request, string $contactMessage): View
    {
        $this->authorizeAbility($request, 'contact-messages.view');

        $message = ContactMessage::query()->findOrFail((int) $contactMessage);

        // Only the first authorized view sets the date: the update is limited to a still-unread row, so a second
        // view (or two admins opening it at once) keeps the original read_at. updated_at is left as it is.
        if ($message->read_at === null) {
            ContactMessage::query()->whereKey($message->getKey())->whereNull('read_at')->toBase()->update(['read_at' => now()]);
            $message->refresh();
        }

        $admin = $request->user('admin');
        $canViewCustomer = (bool) ($admin?->isSuperAdmin() || $admin?->hasAbility('customers.view'));

        // The linked customer account is loaded and shown only to an admin who may view customers.
        if ($canViewCustomer) {
            $message->load('customer:id,name');
        }

        return view('dashboard.contact-messages.show', [
            'contactMessage' => $message,
            'canViewCustomer' => $canViewCustomer,
            'canDelete' => (bool) $admin?->isSuperAdmin(),
        ]);
    }

    /**
     * CONTACT-ADMIN-DELETE-02: soft delete (deleted_at only; the row is kept), for super admins only, checked before
     * the message is read. An unknown or already deleted message is 404. Without the ticked confirmation nothing is
     * deleted and the admin returns to the message. read_at is not touched.
     */
    public function destroy(Request $request, string $contactMessage): RedirectResponse
    {
        abort_unless((bool) $request->user('admin')?->isSuperAdmin(), 403);

        $message = ContactMessage::query()->findOrFail((int) $contactMessage);

        if (! $request->boolean('confirm')) {
            return redirect()->route('dashboard.contact-messages.show', $message->getKey())
                ->with('danger', $this->text('Contact_Messages_Delete_Not_Confirmed', 'The deletion was not confirmed.'));
        }

        $message->delete();

        return redirect()->route('dashboard.contact-messages.index')
            ->with('success', $this->text('Contact_Messages_Deleted', 'The message was deleted.'));
    }

    /** t() without auto-creating the key (AdminContactMessageTranslationSeeder), as in the views. */
    private function text(string $key, string $default): string
    {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
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
