@extends('layouts.storefront')

@section('title', 'تواصل واستفسارات | '.($siteSetting?->site_name ?: 'حِرفة'))

@section('content')
<main>
    <section class="border-b border-line/60 bg-canvas px-4 py-6 lg:px-8"><div class="mx-auto max-w-[1216px] text-start"><p class="text-xs font-bold text-copper">خدمة العملاء</p><h1 class="mt-2 text-[30px] font-bold leading-9 text-ink sm:text-4xl">تواصل واستفسارات</h1></div></section>
    <section class="bg-canvas px-4 py-10 sm:py-12 lg:px-8"><form method="POST" action="{{ route('contact.store') }}" class="mx-auto grid max-w-[760px] gap-4 rounded-[20px] border border-line bg-surface p-6 shadow-card sm:grid-cols-2 sm:p-8">@csrf
        <label class="text-start"><span class="mb-2 block text-sm font-semibold">الاسم</span><input name="name" required value="{{ old('name', $customer?->name) }}" class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none focus:border-olive"></label>
        <label class="text-start"><span class="mb-2 block text-sm font-semibold">البريد الإلكتروني</span><input type="email" name="email" required value="{{ old('email', $customer?->email) }}" class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none focus:border-olive"></label>
        <label class="text-start sm:col-span-2"><span class="mb-2 block text-sm font-semibold">الموضوع</span><input name="subject" value="{{ old('subject') }}" class="h-12 w-full rounded-[14px] border border-line bg-canvas px-4 text-sm outline-none focus:border-olive"></label>
        <label class="text-start sm:col-span-2"><span class="mb-2 block text-sm font-semibold">الرسالة</span><textarea name="message" required rows="6" class="w-full rounded-[14px] border border-line bg-canvas px-4 py-3 text-sm outline-none focus:border-olive">{{ old('message') }}</textarea></label>
        <div class="sm:col-span-2"><button type="submit" class="inline-flex h-11 items-center justify-center rounded-[14px] bg-olive px-5 text-sm font-bold text-surface shadow-sm">إرسال الرسالة</button></div>
    </form></section>
</main>
@endsection
