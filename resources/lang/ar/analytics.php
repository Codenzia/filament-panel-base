<?php

return [
    // Shared — analytics tables absent
    'not_migrated_heading' => 'لم تُنشأ جداول التحليلات',
    'not_migrated_description' => 'نفِّذ الأمر php artisan migrate لإنشاء جداول التحليلات.',

    // Shared — selected range, interpolated into every widget heading
    'range_24h' => 'آخر 24 ساعة',
    'range_7d' => 'آخر 7 أيام',
    'range_30d' => 'آخر 30 يومًا',
    'range_90d' => 'آخر 90 يومًا',

    // Page views chart
    'visitors_heading' => 'مشاهدات الصفحات — :range',
    'visitors_dataset' => 'مشاهدات الصفحات',
    'visitors_empty_heading' => 'لا توجد مشاهدات حتى الآن',
    'visitors_empty_description' => 'ستظهر مشاهدات الصفحات هنا بمجرد أن يبدأ الزوار بتصفح موقعك.',

    // Device types chart
    'devices_heading' => 'أنواع الأجهزة — :range',
    'devices_dataset' => 'مشاهدات الصفحات',
    'devices_empty_heading' => 'لا توجد بيانات أجهزة حتى الآن',
    'devices_empty_description' => 'سيظهر توزيع الأجهزة هنا بمجرد أن يبدأ الزوار بتصفح موقعك.',
    'device_desktop' => 'حاسوب مكتبي',
    'device_mobile' => 'هاتف محمول',
    'device_tablet' => 'جهاز لوحي',
    'device_unknown' => 'غير معروف',

    // Failed logins chart
    'failed_logins_heading' => 'محاولات الدخول الفاشلة — :range',
    'failed_logins_dataset' => 'محاولات الدخول الفاشلة',
    'failed_logins_description' => 'الارتفاع المستمر في المحاولات يشير عادةً إلى هجوم حشو بيانات اعتماد.',
    'failed_logins_empty_heading' => 'لا توجد محاولات دخول فاشلة',
    'failed_logins_empty_description' => 'لم تُسجَّل أي محاولة دخول فاشلة خلال هذه الفترة.',

    // Signup funnel
    'auth_funnel_heading' => 'مسار التسجيل — :range',

    // Error stats
    'errors_heading' => 'الأخطاء — :range',

    // List widgets
    'top_pages_description' => 'أكثر المسارات زيارةً خلال :range.',
    'slowest_pages_description' => 'المسارات الأطول في متوسط زمن الاستجابة خلال :range.',
    'geo_description' => 'توزّع الزوار حسب الدولة خلال :range.',
];
