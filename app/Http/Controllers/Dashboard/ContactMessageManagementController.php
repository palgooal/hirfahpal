<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * CONTACT-ADMIN-IMPLEMENTATION-01: the storefront contact messages in the admin dashboard.
 * Access needs contact-messages.view (super admins always pass, DEC-CONTACT-ADMIN-02). The details page marks the
 * message read the first time an authorized admin opens it and keeps that date afterwards (DEC-CONTACT-ADMIN-01).
 * CONTACT-ADMIN-DELETE-02: a super admin may soft-delete a message (destroy). CONTACT-ADMIN-TRASH-02: a super admin
 * may list the soft-deleted messages (trash, trashShow), restore one, or delete it permanently with a typed phrase
 * (purge). The message text is never logged.
 */
class ContactMessageManagementController extends Controller
{
    /**
     * CONTACT-ADMIN-TRASH-02 (decision 2): the phrase to type before a permanent deletion, per locale. Kept in code,
     * not in translations, so the phrase shown and the phrase checked are always the same and can never be emptied.
     */
    private const PURGE_PHRASES = [
        'ar' => 'حذف نهائي',
        'en' => 'delete permanently',
    ];

    public function index(Request $request): View
    {
        $this->authorizeAbility($request, 'contact-messages.view');

        $messages = ContactMessage::query()
            ->select(['id', 'customer_id', 'name', 'email', 'subject', 'read_at', 'created_at'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.contact-messages.index', [
            'messages' => $messages,
            // The Inbox / Trash switcher and its count, for super admins only (CONTACT-ADMIN-TRASH-02, decision 1).
            'trashCount' => $this->isSuperAdmin($request) ? ContactMessage::onlyTrashed()->count() : null,
        ]);
    }

    /**
     * CONTACT-ADMIN-TRASH-02: the soft-deleted messages, most recently deleted first, super admins only (checked
     * before any message is read).
     */
    public function trash(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        $messages = ContactMessage::onlyTrashed()
            ->select(['id', 'customer_id', 'name', 'email', 'subject', 'read_at', 'created_at', 'deleted_at'])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('dashboard.contact-messages.trash', [
            'messages' => $messages,
            'trashCount' => $messages->total(),
        ]);
    }

    /**
     * CONTACT-ADMIN-TRASH-02: one soft-deleted message, on the details view in its Trash context. Opening it never
     * changes read_at (decision 5). An active, unknown or permanently deleted message is 404.
     */
    public function trashShow(Request $request, string $contactMessage): View
    {
        $this->authorizeSuperAdmin($request);

        $message = ContactMessage::onlyTrashed()->findOrFail((int) $contactMessage);
        $message->load('customer:id,name');

        return view('dashboard.contact-messages.show', [
            'contactMessage' => $message,
            'canViewCustomer' => true,
            'canDelete' => false,
            'inTrash' => true,
            'purgePhrase' => $this->purgePhrase(),
        ]);
    }

    /**
     * CONTACT-ADMIN-TRASH-02: restores a soft-deleted message (deleted_at cleared by SoftDeletes::restore(); read_at,
     * the sender, the content and the customer link are not touched). The row is read locked in a transaction, so a
     * concurrent or repeated restore or permanent deletion finds nothing to act on and gets 404. No confirmation
     * (decision 3).
     */
    public function restore(Request $request, string $contactMessage): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $restored = DB::transaction(function () use ($contactMessage): bool {
            $message = ContactMessage::onlyTrashed()->whereKey((int) $contactMessage)->lockForUpdate()->first();

            return $message !== null && $message->restore();
        });

        abort_unless($restored, 404);

        return redirect()->route('dashboard.contact-messages.trash')
            ->with('success', $this->text('Contact_Messages_Restored', 'The message was restored.'));
    }

    /**
     * CONTACT-ADMIN-TRASH-02: permanently deletes a message that is already in the Trash (forceDelete()), only with
     * the typed phrase of the current locale (decision 2). Without it nothing is deleted and the admin returns to the
     * message. The row is read locked in a transaction, so a concurrent restore or deletion makes this one 404.
     */
    public function purge(Request $request, string $contactMessage): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $id = ContactMessage::onlyTrashed()->whereKey((int) $contactMessage)->value('id');
        abort_if($id === null, 404);

        if (! $this->purgeConfirmed($request->input('confirmation_phrase'))) {
            return redirect()->route('dashboard.contact-messages.trash.show', $id)
                ->with('danger', $this->text('Contact_Messages_Purge_Not_Confirmed', 'The permanent deletion was not confirmed. Type the phrase exactly as shown.'));
        }

        $purged = DB::transaction(function () use ($id): bool {
            $message = ContactMessage::onlyTrashed()->whereKey($id)->lockForUpdate()->first();

            return $message !== null && (bool) $message->forceDelete();
        });

        abort_unless($purged, 404);

        return redirect()->route('dashboard.contact-messages.trash')
            ->with('success', $this->text('Contact_Messages_Purged', 'The message was permanently deleted.'));
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

    /** The phrase of the current locale (English for any other locale); never empty. */
    private function purgePhrase(): string
    {
        return self::PURGE_PHRASES[app()->getLocale()] ?? self::PURGE_PHRASES['en'];
    }

    /**
     * The typed phrase must equal the current locale's phrase after removing invisible direction and zero-width marks,
     * trimming and collapsing whitespace; English is compared case-insensitively. An empty or non-string value never
     * matches.
     */
    private function purgeConfirmed(mixed $typed): bool
    {
        if (! is_string($typed)) {
            return false;
        }

        $normalize = function (string $value): string {
            $value = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $value) ?? '';

            return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? '');
        };

        $expected = $normalize($this->purgePhrase());
        $typed = $normalize($typed);

        if ($typed === '' || $expected === '') {
            return false;
        }

        return app()->getLocale() === 'ar'
            ? $typed === $expected
            : mb_strtolower($typed) === mb_strtolower($expected);
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

    private function isSuperAdmin(Request $request): bool
    {
        return (bool) $request->user('admin')?->isSuperAdmin();
    }

    /** Trash, restore and permanent deletion: super admins only (Admin::isSuperAdmin()), before any message is read. */
    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($this->isSuperAdmin($request), 403);
    }
}
