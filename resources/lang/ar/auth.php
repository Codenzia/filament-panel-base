<?php

return [
    // Generic
    'name' => 'الاسم',
    'email' => 'البريد الإلكتروني',
    'phone' => 'رقم الهاتف',
    'password' => 'كلمة المرور',
    'password_confirmation' => 'تأكيد كلمة المرور',
    'remember_me' => 'تذكرني',
    'submit' => 'إرسال',
    'cancel' => 'إلغاء',

    // Register
    'register_title' => 'إنشاء حساب',
    'register_subtitle' => 'ابدأ بإنشاء حسابك.',
    'register_submit' => 'إنشاء حساب',
    'already_have_account' => 'لديك حساب بالفعل؟',
    'sign_in' => 'تسجيل الدخول',

    // Login
    'login_title' => 'مرحباً بعودتك',
    'login_subtitle' => 'سجل الدخول للمتابعة.',
    'login_submit' => 'تسجيل الدخول',
    'forgot_password' => 'نسيت كلمة المرور؟',
    'no_account_yet' => 'ليس لديك حساب؟',
    'create_account' => 'أنشئ حساباً',
    'credentials_mismatch' => 'بيانات الاعتماد هذه لا تتطابق مع سجلاتنا.',
    'account_suspended' => 'تم تعليق حسابك. يرجى التواصل مع الدعم.',
    'account_pending' => 'حسابك في انتظار الموافقة. سيتم إعلامك عند الموافقة.',

    // Identifier label
    'identifier_email' => 'البريد الإلكتروني',
    'identifier_phone' => 'رقم الهاتف',
    'identifier_either' => 'البريد الإلكتروني أو الهاتف',

    // Email verification
    'verify_email_title' => 'تحقق من بريدك الإلكتروني',
    'verify_email_intro' => 'لقد أرسلنا رابط التحقق إلى :email. افتح الرابط لتفعيل حسابك.',
    'verify_email_resend' => 'إعادة إرسال رابط التحقق',
    'verify_email_resent' => 'تم إرسال رابط تحقق جديد.',
    'verify_email_done' => 'بريدك الإلكتروني تم التحقق منه بالفعل.',

    // OTP verification
    'verify_otp_title' => 'تحقق من :channel',
    'verify_otp_intro' => 'أدخل الرمز المكون من :length أرقام الذي أرسلناه إلى :target.',
    'verify_otp_submit' => 'تحقق',
    'verify_otp_resend' => 'إعادة إرسال الرمز',
    'verify_otp_resent' => 'تم إرسال رمز جديد.',
    'verify_otp_invalid' => 'رمز التحقق غير صالح أو منتهي الصلاحية.',
    'verify_otp_rate_limited' => 'محاولات كثيرة. حاول مرة أخرى خلال :seconds ثانية.',

    // OTP delivery
    'otp_email_subject' => 'رمز التحقق من :brand',
    'otp_email_greeting' => 'مرحباً,',
    'otp_email_intro' => 'استخدم الرمز أدناه لإكمال تسجيل الدخول أو التسجيل في :brand.',
    'otp_email_ttl' => 'تنتهي صلاحية هذا الرمز خلال :minutes دقائق.',
    'otp_email_ignore' => 'إذا لم تطلب هذا الرمز، يمكنك تجاهل هذا البريد بأمان.',
    'otp_sms_body' => 'رمز التحقق من :brand هو :code',
    'otp_rate_limited' => 'يرجى الانتظار :seconds ثانية قبل طلب رمز آخر.',

    // Forgot / reset password
    'forgot_title' => 'إعادة تعيين كلمة المرور',
    'forgot_subtitle' => 'أدخل بريدك الإلكتروني وسنرسل لك رابط إعادة تعيين.',
    'forgot_submit' => 'إرسال الرابط',
    'forgot_sent' => 'إذا كان هذا البريد مسجلاً لدينا، فقد أرسلنا رابط إعادة التعيين.',
    'reset_title' => 'اختر كلمة مرور جديدة',
    'reset_submit' => 'تحديث كلمة المرور',
    'reset_done' => 'تم تحديث كلمة المرور. يمكنك الآن تسجيل الدخول.',

    // Social
    'continue_with' => 'المتابعة باستخدام :provider',
    'or_continue_with' => 'أو المتابعة باستخدام',
    'social_link_existing_email' => 'يوجد حساب مسجل بهذا البريد الإلكتروني. سجّل الدخول بطريقتك الأصلية، ثم اربط :provider من ملفك الشخصي.',
    'social_missing_email' => 'حساب :provider الخاص بك لم يشارك عنوان بريد إلكتروني. سجّل باستخدام البريد الإلكتروني بدلاً من ذلك، أو تواصل مع الدعم.',
    'social_link_owned_by_other' => 'حساب :provider هذا مرتبط بمستخدم آخر. افصل ربطه من ذلك الحساب أولاً.',
    'social_link_success' => 'تم ربط :provider بحسابك.',
    'oauth_invalid_state' => 'انتهت جلسة تسجيل الدخول. يرجى المحاولة مرة أخرى.',
    'oauth_provider_error' => 'فشل تسجيل الدخول عبر :provider. يرجى المحاولة مرة أخرى أو استخدام طريقة أخرى.',

    // Manage connected accounts
    'social_manage_title' => 'الحسابات المرتبطة',
    'social_manage_subtitle' => 'اربط مزودي الدخول لتسجيل دخول أسرع، أو افصل ما لم تعد تستخدمه.',
    'social_connected' => 'المرتبط',
    'social_available' => 'المتاح',
    'social_connect' => 'ربط :provider',
    'social_disconnect' => 'فصل',
    'social_disconnect_locked' => 'طريقة الدخول الوحيدة',
    'social_disconnect_confirm' => 'فصل :provider عن حسابك؟',
    'social_disconnect_success' => 'تم فصل :provider.',
    'social_disconnect_blocked' => 'عيّن كلمة مرور قبل فصل طريقة الدخول الوحيدة لديك.',
    'social_none_configured' => 'لم يتم تفعيل أي مزود لتسجيل الدخول لهذا الموقع.',

    // Channel labels
    'channel.email' => 'البريد الإلكتروني',
    'channel.whatsapp' => 'واتساب',
    'channel.twilio' => 'رسالة نصية',
    'channel.vonage' => 'رسالة نصية',
    'channel.null' => 'السجل',

    // Moderation
    'moderation_approved_subject' => 'تمت الموافقة على حسابك في :brand',
    'moderation_approved_body' => 'مرحباً بك في :brand! حسابك الآن مفعّل. سجل الدخول للبدء.',
    'moderation_suspended_subject' => 'تم تعليق حسابك في :brand',
    'moderation_suspended_body' => 'تم تعليق حسابك. السبب: :reason',

    // Validation
    'email_disposable' => 'نطاق :attribute غير مسموح. يرجى استخدام بريد إلكتروني دائم.',
    'email_domain_not_allowed' => 'التسجيل مقتصر على نطاقات البريد المعتمدة. يرجى استخدام بريد مؤسستك.',
    'phone_invalid' => ':attribute ليس رقم هاتف صالح.',
    'phone_format_invalid' => 'صيغة :attribute غير صالحة. استخدم الصيغة الدولية، مثل +14155552671.',
    'throttle_rate_limited' => 'محاولات كثيرة. حاول مرة أخرى خلال :seconds ثانية.',

    // صفحة إدارة إعدادات المصادقة
    'settings_nav_label' => 'المصادقة',
    'settings_nav_group' => 'الإعدادات',
    'settings_title' => 'إعدادات المصادقة',
    'settings_save' => 'حفظ التغييرات',
    'settings_saved' => 'تم حفظ إعدادات المصادقة.',

    'settings_section_registration' => 'التسجيل وتسجيل الدخول',
    'settings_section_registration_description' => 'تحكم في كيفية انضمام المستخدمين الجدد وبأي معرف يسجّلون الدخول.',
    'settings_registration_mode' => 'وضع التسجيل',
    'settings_registration_mode_open' => 'مفتوح — يمكن لأي شخص التسجيل',
    'settings_registration_mode_moderated' => 'مُعتمد — يجب موافقة المسؤول',
    'settings_registration_mode_help' => 'الوضع المُعتمد يضع المستخدمين الجدد في حالة انتظار حتى يوافق المسؤول.',
    'settings_credentials_mode' => 'المعرف',
    'settings_credentials_mode_email' => 'البريد الإلكتروني فقط',
    'settings_credentials_mode_phone' => 'الهاتف فقط',
    'settings_credentials_mode_both' => 'البريد الإلكتروني أو الهاتف',
    'settings_credentials_mode_help' => 'يحدد الحقل الذي يسجّل به المستخدمون والحقول التي يجمعها نموذج التسجيل.',
    'settings_phone_required' => 'رقم الهاتف مطلوب',
    'settings_phone_required_help' => 'عند التفعيل والمعرف "البريد الإلكتروني أو الهاتف"، يبقى الهاتف مطلوبًا عند التسجيل.',
    'settings_default_country_code' => 'رمز الدولة الافتراضي',
    'settings_disposable_email_blocking' => 'حظر نطاقات البريد المؤقت',
    'settings_disposable_email_blocking_help' => 'رفض التسجيل باستخدام مزودي البريد المؤقت.',
    'settings_allowed_email_domains' => 'حصر التسجيل في نطاقات البريد التالية',
    'settings_allowed_email_domains_help' => 'اتركه فارغًا للسماح بأي نطاق. أضف مثلاً "acme.com" للسماح بهذا النطاق ونطاقاته الفرعية فقط. يعمل مع وضع التسجيل أعلاه.',

    'settings_section_verification' => 'التحقق',
    'settings_section_verification_description' => 'حدّد القنوات التي يجب التحقق منها قبل منح الوصول.',
    'settings_require_email_verification' => 'طلب التحقق من البريد الإلكتروني',
    'settings_require_phone_verification' => 'طلب التحقق من الهاتف',

    'settings_section_otp' => 'الرموز لمرة واحدة (OTP)',
    'settings_section_otp_description' => 'المزود ومدة صلاحية رموز التحقق المرسلة أثناء التسجيل وتسجيل الدخول.',
    'settings_otp_driver' => 'المزود النشط',
    'settings_allowed_otp_drivers' => 'المزودات المسموحة',
    'settings_allowed_otp_drivers_help' => 'المزودات التي يمكن للمسؤول التبديل إليها. أضف أو احذف من حقل العلامات.',
    'settings_otp_code_length' => 'طول الرمز (أرقام)',
    'settings_otp_ttl_minutes' => 'مدة صلاحية الرمز (دقائق)',

    'settings_section_social' => 'تسجيل الدخول الاجتماعي',
    'settings_section_social_description' => 'مزودو OAuth الذين يظهرون في صفحتي تسجيل الدخول والتسجيل.',
    'settings_social_providers_enabled' => 'المزودات المفعّلة',
    'settings_social_providers_enabled_help' => 'مفاتيح المزودات (حروف صغيرة، مثل "google" و"github"). يجب توفر بيانات اعتماد لكل منها في config/services.php.',
    'settings_social_email_linking' => 'سياسة تعارض البريد الإلكتروني',
    'settings_social_email_linking_require_login' => 'يلزم تسجيل الدخول أولاً (الأكثر أماناً)',
    'settings_social_email_linking_trust_verified' => 'الربط التلقائي عند التحقق من الطرفين',
    'settings_social_email_linking_auto' => 'الربط التلقائي بدون شروط (غير آمن)',
    'settings_social_email_linking_help' => 'ما يحدث عندما يُعيد تسجيل الدخول الاجتماعي بريداً إلكترونياً يخص حسابًا محليًا موجودًا.',
    'settings_social_trust_verified_email' => 'الوثوق بتحقق المزود من البريد الإلكتروني',
    'settings_social_trust_verified_email_help' => 'عندما يؤكد المزود التحقق من البريد، تخطّى تدفق التحقق داخل التطبيق.',

    'settings_section_throttle' => 'الحد من المعدل',
    'settings_section_throttle_description' => 'الحماية من القوة الغاشمة لتدفقات تسجيل الدخول والتسجيل وOTP وإعادة تعيين كلمة المرور.',
    'settings_throttle_per_minute' => 'المحاولات في الدقيقة',
    'settings_throttle_per_day' => 'المحاولات في اليوم',
];
