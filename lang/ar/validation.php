<?php

return [
    'accepted' => 'يجب قبول :attribute.',
    'email' => 'يجب أن يكون :attribute بريد إلكتروني صالح.',
    'required' => ':attribute مطلوب.',
    'unique' => ':attribute مستخدم من قبل.',
    'string' => 'يجب أن يكون :attribute نص.',
    'max' => [
        'string' => 'يجب ألا يكون عدد أحرف :attribute أكبر من :max.',
    ],
    
    'custom' => [
        'email' => [
            'invalid_format' => 'يجب أن يكون البريد الإلكتروني صالح.',
            'fake_email' => 'يبدو أن البريد الإلكتروني غير صالح. الرجاء استخدام بريد إلكتروني حقيقي.',
            'temp_email' => 'البريد الإلكتروني المؤقت غير مسموح به. الرجاء استخدام بريد إلكتروني دائم.',
            'invalid_domain' => 'يجب أن يحتوي البريد الإلكتروني على نطاق صالح.',
            'invalid_tld' => 'يجب أن يحتوي البريد الإلكتروني على امتداد نطاق صالح.',
            'domain_appears_invalid' => 'يبدو أن نطاق البريد الإلكتروني غير صالح.',
            'domain_cannot_receive_email' => 'يبدو أن نطاق البريد الإلكتروني غير مهيأ لاستقبال الرسائل.',
            'suspicious_email' => 'يبدو أن البريد الإلكتروني غير صالح. الرجاء استخدام بريد إلكتروني حقيقي.',
            'invalid_email_format' => 'صيغة البريد الإلكتروني غير صالحة.',
        ],
        'acknowledgment_accepted' => [
            'accepted' => 'يجب قبول الإقرار.',
        ],
        'undertaking_accepted' => [
            'accepted' => 'يجب قبول اتفاقية التعهد.',
        ],
    ],
    
    'attributes' => [
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'phone' => 'رقم الهاتف',
        'acknowledgment_accepted' => 'الإقرار',
        'undertaking_accepted' => 'التعهد',
    ],
];
