{{--
    CONTACT-ADMIN-IMPLEMENTATION-01: one storefront contact message, on the pattern of the customer details page
    (back link and title, then admin-product-facts panels). Opening it marks the message read once
    (ContactMessageManagementController::show). The message keeps its line breaks through the escaped-only
    admin-product-text style; nothing is rendered as HTML. The linked customer account is shown only when
    $canViewCustomer (customers.view). Texts through t() without auto-creating keys (AdminContactMessageTranslationSeeder).
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
    $subject = filled($contactMessage->subject) ? $contactMessage->subject : null;
@endphp

<x-dashboard-layout>
    <x-slot:pageTitle>{{ $msgText('Nav_Contact_Messages', 'Contact messages') }}</x-slot:pageTitle>

    <div class="col-span-12 admin-media admin-products admin-product-show admin-contact-message-show" id="admin-contact-message-show">

        {{-- Content header (5.1): the page title is in the admin header; the message is named here. --}}
        <div class="admin-media-head admin-product-head">
            <div class="admin-product-identity">
                <a href="{{ route('dashboard.contact-messages.index') }}" class="admin-product-back">
                    <i class="ph-duotone ph-arrow-left admin-product-back-arrow" aria-hidden="true"></i>
                    <span>{{ $msgText('Contact_Messages_Show_Back', 'Back to contact messages') }}</span>
                </a>
                <h2 class="admin-product-title"><bdi>{{ $subject ?? $msgText('Contact_Messages_No_Subject', 'No subject') }}</bdi></h2>
                <p class="admin-products-badges">
                    <span class="admin-products-badge is-draft">{{ $msgText('Contact_Messages_Status_Read', 'Read') }}</span>
                </p>
            </div>
        </div>

        {{-- 1. Sender --}}
        <section class="card admin-media-panel admin-product-panel" aria-labelledby="contact-message-heading-sender">
            <h2 class="admin-product-panel-title" id="contact-message-heading-sender">{{ $msgText('Contact_Messages_Show_Sender', 'Sender details') }}</h2>
            <dl class="admin-product-facts">
                <div><dt>{{ $msgText('Contact_Messages_Show_Name', 'Name') }}</dt><dd><bdi>{{ $contactMessage->name }}</bdi></dd></div>
                <div><dt>{{ $msgText('Contact_Messages_Show_Email', 'Email') }}</dt><dd><span dir="ltr">{{ $contactMessage->email }}</span></dd></div>
                @if ($canViewCustomer)
                    <div data-contact-message-account>
                        <dt>{{ $msgText('Contact_Messages_Show_Account', 'Customer account') }}</dt>
                        <dd>
                            @if ($contactMessage->customer)
                                <a href="{{ route('dashboard.customers.show', $contactMessage->customer) }}"><bdi>{{ $contactMessage->customer->name }}</bdi></a>
                            @else
                                {{ $msgText('Contact_Messages_Show_Guest', 'Guest (no account)') }}
                            @endif
                        </dd>
                    </div>
                @endif
                <div><dt>{{ $msgText('Contact_Messages_Show_Received', 'Received') }}</dt><dd><span dir="ltr">{{ $when($contactMessage->created_at) }}</span></dd></div>
                <div><dt>{{ $msgText('Contact_Messages_Show_Read_At', 'Read on') }}</dt><dd><span dir="ltr" data-contact-message-read-at>{{ $when($contactMessage->read_at) }}</span></dd></div>
            </dl>
        </section>

        {{-- 2. Message --}}
        <section class="card admin-media-panel admin-product-panel" aria-labelledby="contact-message-heading-body">
            <h2 class="admin-product-panel-title" id="contact-message-heading-body">{{ $msgText('Contact_Messages_Show_Message', 'Message') }}</h2>
            <dl class="admin-product-facts">
                <div><dt>{{ $msgText('Contact_Messages_Column_Subject', 'Subject') }}</dt><dd><bdi>{{ $subject ?? $msgText('Contact_Messages_No_Subject', 'No subject') }}</bdi></dd></div>
                <div><dt>{{ $msgText('Contact_Messages_Show_Message', 'Message') }}</dt><dd class="admin-product-text" dir="auto" data-contact-message-body>{{ $contactMessage->message }}</dd></div>
            </dl>
        </section>
    </div>
</x-dashboard-layout>
