{{--
    CONTACT-ADMIN-IMPLEMENTATION-01: the storefront contact messages in the admin shell, on the pattern of the customers
    list (CUS-UI-05): the admin-products table on wide screens that becomes one card per message below 1024px, the
    shared badges and the shared pagination. No CSS or JavaScript of its own.
    Newest first, 15 per page (ContactMessageManagementController::index). Texts through t() without auto-creating keys
    (AdminContactMessageTranslationSeeder; the title and sidebar label from AdminDashboardTranslationSeeder).
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

    <div class="col-span-12 admin-media admin-products admin-contact-messages" id="admin-contact-messages">

        {{-- Content header (5.1): the page title is in the admin header. --}}
        <div class="admin-media-head">
            <p class="admin-media-intro">{{ $msgText('Contact_Messages_Intro', 'Messages sent from the store contact page.') }}</p>
        </div>

        {{-- Result messages (success, danger), inline as on the other admin lists (CONTACT-ADMIN-DELETE-02). --}}
        @foreach (['success' => 'success', 'danger' => 'error'] as $flash => $tone)
            @if (session($flash))
                <div class="admin-media-alert is-{{ $tone }}" role="{{ $tone === 'success' ? 'status' : 'alert' }}">
                    <i class="ph-duotone {{ $tone === 'success' ? 'ph-check-circle' : 'ph-info' }}" aria-hidden="true"></i>
                    <div class="admin-media-alert-content"><p>{{ session($flash) }}</p></div>
                </div>
            @endif
        @endforeach

        <section class="card admin-media-panel" aria-labelledby="contact-messages-list-title">
            <h2 class="visually-hidden" id="contact-messages-list-title">{{ $msgText('Nav_Contact_Messages', 'Contact messages') }}</h2>

            <div class="card-body">
                @if ($messages->isEmpty())
                    <div class="admin-media-state" role="status">
                        <i class="ph-duotone ph-envelope-simple" aria-hidden="true"></i>
                        @if ($messages->total() > 0)
                            {{-- A page past the last one: point back to the last page. --}}
                            <span>{{ $msgText('Contact_Messages_Empty_Title', 'No messages yet') }}</span>
                            <a href="{{ $messages->url($messages->lastPage()) }}" class="btn btn-light-secondary">{{ str_replace(':page', (string) $messages->lastPage(), $msgText('Pagination_Page', 'Page :page')) }}</a>
                        @else
                            <span>{{ $msgText('Contact_Messages_Empty_Title', 'No messages yet') }}</span>
                            <span class="admin-media-hint">{{ $msgText('Contact_Messages_Empty_Text', 'Messages sent from the store contact page appear here.') }}</span>
                        @endif
                    </div>
                @else
                    <div class="admin-products-table-wrap">
                        <table class="admin-products-table">
                            <caption class="visually-hidden">{{ $msgText('Nav_Contact_Messages', 'Contact messages') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ $msgText('Contact_Messages_Column_Sender', 'Sender') }}</th>
                                    <th scope="col">{{ $msgText('Contact_Messages_Column_Subject', 'Subject') }}</th>
                                    <th scope="col">{{ $msgText('Contact_Messages_Column_Received', 'Received') }}</th>
                                    <th scope="col">{{ $msgText('Contact_Messages_Column_Status', 'Status') }}</th>
                                    <th scope="col" class="is-actions"><span class="visually-hidden">{{ $msgText('Actions', 'Actions') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($messages as $contactMessage)
                                    <tr data-contact-message-id="{{ $contactMessage->id }}" data-read="{{ $contactMessage->read_at ? 'true' : 'false' }}">
                                        <td class="admin-products-cell-product">
                                            <p class="admin-products-name"><a href="{{ route('dashboard.contact-messages.show', $contactMessage->id) }}" class="admin-products-name-link"><bdi>{{ $contactMessage->name }}</bdi></a></p>
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
                                            <span class="admin-products-label">{{ $msgText('Contact_Messages_Column_Status', 'Status') }}</span>
                                            @if ($contactMessage->read_at)
                                                <span class="admin-products-badge is-draft">{{ $msgText('Contact_Messages_Status_Read', 'Read') }}</span>
                                            @else
                                                <span class="admin-products-badge is-low">{{ $msgText('Contact_Messages_Status_Unread', 'New') }}</span>
                                            @endif
                                        </td>
                                        <td class="is-actions">
                                            <a href="{{ route('dashboard.contact-messages.show', $contactMessage->id) }}" class="btn btn-sm btn-light-secondary">
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
