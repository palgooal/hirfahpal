<?php

return [
    // Per-vendor delivery fee, calculated by the server; never accepted from a browser.
    'delivery_fee' => 0,
    'order_statuses' => [
        'pending' => 'قيد الانتظار', 'processing' => 'قيد التنفيذ', 'completed' => 'مكتمل',
        'partially_cancelled' => 'ملغي جزئياً', 'cancelled' => 'ملغي',
    ],
    'vendor_statuses' => [
        'pending' => 'قيد الانتظار', 'accepted' => 'مقبول', 'preparing' => 'قيد التجهيز',
        'ready_for_delivery' => 'جاهز للتوصيل', 'assigned' => 'تم تعيين مندوب', 'out_for_delivery' => 'خارج للتوصيل',
        'delivered' => 'تم التسليم', 'completed' => 'مكتمل', 'rejected' => 'مرفوض', 'cancelled' => 'ملغي',
    ],
];
