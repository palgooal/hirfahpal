{{--
    CONTACT-ADMIN-TRASH-02 (decision 1): the Inbox / Trash switcher of the contact messages, shown to super admins only
    (the parent includes it only when $trashCount is not null). $current is 'inbox' or 'trash'; $msgText is the
    parent's t() reader without auto-create. Plain links with existing Bootstrap and theme classes; no CSS or JS.
--}}
<nav class="d-flex flex-wrap gap-2" aria-label="{{ $msgText('Contact_Messages_Tabs_Label', 'Contact message views') }}" data-contact-messages-switcher>
    <a href="{{ route('dashboard.contact-messages.index') }}" class="btn btn-sm {{ $current === 'inbox' ? 'btn-primary' : 'btn-light-secondary' }}" @if ($current === 'inbox') aria-current="page" @endif>
        <i class="ph-duotone ph-tray" aria-hidden="true"></i>
        <span>{{ $msgText('Contact_Messages_Tab_Inbox', 'Inbox') }}</span>
    </a>
    <a href="{{ route('dashboard.contact-messages.trash') }}" class="btn btn-sm {{ $current === 'trash' ? 'btn-primary' : 'btn-light-secondary' }}" @if ($current === 'trash') aria-current="page" @endif>
        <i class="ph-duotone ph-trash" aria-hidden="true"></i>
        <span>{{ $msgText('Contact_Messages_Tab_Trash', 'Trash') }}</span>
        <span dir="ltr" data-contact-messages-trash-count>({{ (int) $trashCount }})</span>
    </a>
</nav>
