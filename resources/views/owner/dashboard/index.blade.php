<x-owner-dashboard-layout>
    <x-slot:breadcrumbs>
        <li class="breadcrumb-item" aria-current="page">
            لوحة صاحب الحساب
        </li>
    </x-slot:breadcrumbs>

    <div class="col-span-12">
        <div class="card">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                <div>
                    <p class="text-muted mb-1">لوحة التحكم</p>
                    <h4 class="mb-2">مرحباً بك في داشبورد صاحب الحساب</h4>
                    <p class="text-muted mb-0">
                        هذه صفحة نظيفة جاهزة لبناء مشروعك الجديد داخل نفس قالب داشبورد المالك.
                    </p>
                </div>
                <div class="avtar avtar-xl bg-light-primary">
                    <i class="ti ti-layout-dashboard f-30"></i>
                </div>
            </div>
        </div>
    </div>

    @foreach ([
        ['الوحدات', 'جاهزة للربط', 'primary', 'ti-apps'],
        ['المحتوى', 'جاهز للبناء', 'success', 'ti-file-text'],
        ['النشاط', 'حسب المشروع الجديد', 'info', 'ti-activity'],
        ['الإعدادات', 'قابلة للتخصيص', 'warning', 'ti-settings'],
    ] as [$label, $value, $color, $icon])
        <div class="col-span-12 sm:col-span-6 xl:col-span-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <p class="text-muted mb-1">{{ $label }}</p>
                            <h5 class="mb-0">{{ $value }}</h5>
                        </div>
                        <div class="avtar avtar-s bg-light-{{ $color }}">
                            <i class="ti {{ $icon }} f-20"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="col-span-12 xl:col-span-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">مساحة العمل الرئيسية</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>العنصر</th>
                                <th>الحالة</th>
                                <th>ملاحظة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>قالب داشبورد المالك</td>
                                <td><span class="badge bg-success">موجود</span></td>
                                <td>تم الإبقاء على layout والكلاسات الخاصة بالداشبورد.</td>
                            </tr>
                            <tr>
                                <td>كود المشروع القديم</td>
                                <td><span class="badge bg-secondary">تم تنظيفه</span></td>
                                <td>تم حذف الاعتماد على بيانات المشروع القديم.</td>
                            </tr>
                            <tr>
                                <td>المشروع الجديد</td>
                                <td><span class="badge bg-warning">جاهز</span></td>
                                <td>يمكنك الآن تركيب أقسام owner dashboard الخاصة بالمشروع الجديد.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">إجراءات سريعة</h5>
            </div>
            <div class="card-body d-grid gap-2">
                <a href="{{ route('owner.dashboard') }}" class="btn btn-outline-secondary">الرئيسية</a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <span class="badge bg-light-primary text-primary">نظيف</span>
                <h5 class="mt-3 mb-2">ملاحظات الصفحة</h5>
                <p class="text-muted mb-0">
                    الصفحة لا تستخدم أي متغيرات من الكنترولر، لذلك ستعمل كداشبورد أساسي إلى أن تضيف بيانات المشروع الجديد.
                </p>
            </div>
        </div>
    </div>
</x-owner-dashboard-layout>
