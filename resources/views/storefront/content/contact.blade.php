@extends('layouts.storefront')

@section('title', 'تواصل واستفسارات | '.($siteSetting?->site_name ?: 'حِرفة'))

{{-- CONTACT-PAGE-CLOSURE-02: success and validation feedback are shown inside the form card, so the layout's
     page-level message is skipped here (see layouts/storefront). --}}
@section('inline_feedback', '1')

@php
  $fieldClass = fn (string $field) => $errors->has($field) ? 'border-[#A13D2B]/60' : 'border-line';
@endphp

@section('content')
<main>
    <section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8"><div class="mx-auto max-w-[1216px] text-start"><p class="text-xs font-bold text-copper">خدمة العملاء</p><h1 class="mt-2 text-[30px] font-bold leading-9 text-ink sm:text-4xl">تواصل واستفسارات</h1></div></section>
    <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8"><form method="POST" action="{{ route('contact.store') }}" class="mx-auto grid max-w-[760px] gap-4 rounded-[20px] border border-line bg-surface p-6 shadow-card sm:grid-cols-2 sm:p-8">@csrf
        @if (session('success'))
            <div class="flex items-center gap-3 rounded-[14px] border border-sage/40 bg-sage/10 px-4 py-3 text-start sm:col-span-2" role="status" data-contact-success>
                <i data-lucide="check-circle-2" class="h-5 w-5 shrink-0 text-olive" aria-hidden="true"></i>
                <p class="text-sm font-semibold leading-6 text-olive">{{ session('success') }}</p>
            </div>
        @endif
        @if ($errors->any())
            <div class="flex items-center gap-3 rounded-[14px] border border-[#A13D2B]/30 bg-[#A13D2B]/10 px-4 py-3 text-start sm:col-span-2" role="alert" data-contact-errors>
                <i data-lucide="circle-alert" class="h-5 w-5 shrink-0 text-[#A13D2B]" aria-hidden="true"></i>
                <p class="text-sm font-semibold leading-6 text-[#A13D2B]">تعذّر إرسال الرسالة. يرجى مراجعة الحقول المشار إليها أدناه.</p>
            </div>
        @endif
        <div class="text-start">
            <label for="contact-name"><span class="mb-2 block text-sm font-semibold">الاسم</span><input id="contact-name" name="name" required value="{{ old('name', $customer?->name) }}" @error('name') aria-invalid="true" aria-describedby="contact-name-error" @enderror class="h-12 w-full rounded-[14px] border {{ $fieldClass('name') }} bg-canvas px-4 text-sm outline-none focus:border-olive"></label>
            @error('name')<p id="contact-name-error" class="mt-1.5 text-xs font-semibold text-[#A13D2B]">{{ $message }}</p>@enderror
        </div>
        <div class="text-start">
            <label for="contact-email"><span class="mb-2 block text-sm font-semibold">البريد الإلكتروني</span><input id="contact-email" type="email" name="email" required value="{{ old('email', $customer?->email) }}" @error('email') aria-invalid="true" aria-describedby="contact-email-error" @enderror class="h-12 w-full rounded-[14px] border {{ $fieldClass('email') }} bg-canvas px-4 text-sm outline-none focus:border-olive"></label>
            @error('email')<p id="contact-email-error" class="mt-1.5 text-xs font-semibold text-[#A13D2B]">{{ $message }}</p>@enderror
        </div>
        <div class="text-start sm:col-span-2">
            <label for="contact-subject"><span class="mb-2 block text-sm font-semibold">الموضوع</span><input id="contact-subject" name="subject" value="{{ old('subject') }}" @error('subject') aria-invalid="true" aria-describedby="contact-subject-error" @enderror class="h-12 w-full rounded-[14px] border {{ $fieldClass('subject') }} bg-canvas px-4 text-sm outline-none focus:border-olive"></label>
            @error('subject')<p id="contact-subject-error" class="mt-1.5 text-xs font-semibold text-[#A13D2B]">{{ $message }}</p>@enderror
        </div>
        <div class="text-start sm:col-span-2">
            <label for="contact-message"><span class="mb-2 block text-sm font-semibold">الرسالة</span><textarea id="contact-message" name="message" required rows="6" @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror class="block w-full rounded-[14px] border {{ $fieldClass('message') }} bg-canvas px-4 py-3 text-sm outline-none focus:border-olive">{{ old('message') }}</textarea></label>
            @error('message')<p id="contact-message-error" class="mt-1.5 text-xs font-semibold text-[#A13D2B]">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2"><button type="submit" class="inline-flex h-11 cursor-pointer items-center justify-center rounded-[14px] bg-olive px-5 text-sm font-bold text-surface shadow-sm">إرسال الرسالة</button></div>
    </form></section>
</main>
@endsection
