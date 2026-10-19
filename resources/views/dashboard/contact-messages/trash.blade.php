{{--
    CONTACT-ADMIN-TRASH-02: the soft-deleted contact messages, super admins only (ContactMessageManagementController::
    trash), on the pattern of the inbox list: the admin-products table that becomes one card per message below 1024px
    and the shared pagination. Most recently deleted first, 15 per page. The Inbox / Trash switcher with the Trash
    count. Texts through t() without auto-creating keys (AdminContactMessageTranslationSeeder). No CSS or JS of its own.
--}}
@php
    $msgText = function (string $key, string $default): string {
        $autoCreate = config('palgoals-locale.auto_create');
        config(['palgoals-locale.auto_create' => false]);

        try {
            return t('dashboard.'.$key, $default);
        } finally {
            config(['palgoals-locale.auto_create' => $autoCreate]);
        }
    };

    $when = fn ($date): string => $date ? $date->format('Y-m-d H:i') : '—';
@endphp

<x-dashboard-layout>
    <x-slot:pageTitle>{{ $msgText('Nav_Contact_Messages', 'Contact messages') }}</x-slot:pageTitle>

    <div class="col-span-12 admin-media admin-products admin-contact-messages admin-contact-messages-trash" id="admin-contact-messages-trash">

        {{-- Content header (5.1): the page title is in the admin header. --}}
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $msgText('Contact_Messages_Trash_Intro', 'Deleted messages. A super admin can restore them or delete them permanently.') }}</p>
            @include('dashboard.contact-messages.partials.switcher', ['current' => 'trash', 'trashCount' => $trashCount, 'msgText' => $msgText])
        </div>

        {{-- Result messages (success, danger), inline as on the other admin lists. --}}
        @foreach (['success' => 'success', 'danger' => 'error'] as $flash => $tone)
            @if (session($flash))
                <div class="admin-media-alert is-{{ $tone }}" role="{{ $tone === 'success' ? 'status' : 'alert' }}">
                    <i class="ph-duotone {{ $tone === 'success' ? 'ph-check-circle' : 'ph-info' }}" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session($flash) }}</p></div>
                </div>
            @endif
        @endforeach

        <section class="card admin-media-panel" aria-labelledby="contact-messages-trash-title">
            <h2 class="visually-hidden" id="contact-messages-trash-title">{{ $msgText('Contact_Messages_Tab_Trash', 'Trash') }}</h2>

            <div class="card-body">
                @if ($messages->isEmpty())
                    <div class="admin-media-state" role="status">
                        <i class="ph-duotone ph-trash" aria-hidden="true"></i>
                        @if ($messages->total() > 0)
                            {{-- A page past the last one: point back to the last page. --}}
                            <span>{{ $msgText('Contact_Messages_Trash_Empty_Title', 'The trash is empty') }}</span>
                            <a href="{{ $messages->url($messages->lastPage()) }}" class="btn btn-light-secondary">{{ str_replace(':page', (string) $messages->lastPage(), $msgText('Pagination_Page', 'Page :page')) }}</a>
                        @else
                            <span>{{ $msgText('Contact_Messages_Trash_Empty_Title', 'The trash is empty') }}</span>
                            <span class="admin-media-hint">{{ $msgText('Contact_Messages_Trash_Empty_Text', 'Messages appear here after they are deleted from the inbox.') }}</span>
                        @endif
                    </div>
                @else
                    <div class="admin-products-table-wrap">
                        <table class="admin-products-table">
                            <caption class="visually-hidden">{{ $msgText('Contact_Messages_Tab_Trash', 'Trash') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ $msgText('Contact_Messages_Column_Sender', 'Sender') }}</th>
                                    <th scope="col">{{ $msgText('Contact_Messages_Column_Subject', 'Subject') }}</th>
                                    <th scope="col">{{ $msgText('Contact_Messages_Column_Received', 'Received') }}</th>
                                    <th scope="col">{{ $msgText('Contact_Messages_Column_Deleted', 'Deleted') }}</th>
                                    <th scope="col" class="is-actions"><span class="visually-hidden">{{ $msgText('Actions', 'Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($messages as $contactMessage)
                                    <tr data-trashed-message-id="{{ $contactMessage->id }}">
                                        <td class="admin-products-cell-product">
                                            <p class="admin-products-name"><a href="{{ route('dashboard.contact-messages.trash.show', $contactMessage->id) }}" class="admin-products-name-link"><bdi>{{ $contactMessage->name }}</bdi></a></p>
                                            <p class="admin-products-code" dir="ltr">{{ $contactMessage->email }}</p>
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $msgText('Contact_Messages_Column_Subject', 'Subject') }}</span>
                                            @if (filled($contactMessage->subject))
                                                <bdi>{{ $contactMessage->subject }}</bdi>
                                            @else
                                                <span class="admin-products-muted">{{ $msgText('Contact_Messages_No_Subject', 'No subject') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $msgText('Contact_Messages_Column_Received', 'Received') }}</span>
                                            <time dir="ltr" datetime="{{ $contactMessage->created_at?->toIso8601String() }}">{{ $when($contactMessage->created_at) }}</time>
                                        </td>
                                        <td>
                                            <span class="admin-products-label">{{ $msgText('Contact_Messages_Column_Deleted', 'Deleted') }}</span>
                                            <time dir="ltr" datetime="{{ $contactMessage->deleted_at?->toIso8601String() }}">{{ $when($contactMessage->deleted_at) }}</time>
                                        </td>
                                        <td class="is-actions">
                                            <a href="{{ route('dashboard.contact-messages.trash.show', $contactMessage->id) }}" class="btn btn-sm btn-light-secondary">
                                                <i class="ph-duotone ph-eye" aria-hidden="true"></i>
                                                <span>{{ $msgText('Contact_Messages_View', 'View') }}</span>
                                                <span class="visually-hidden"><bdi>{{ $contactMessage->name }}</bdi></span>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{ $messages->links('dashboard.partials.pagination') }}
                @endif
            </div>
        </section>
    </div>
</x-dashboard-layout>
