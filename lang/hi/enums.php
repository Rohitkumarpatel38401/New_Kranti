<?php

return [
    'institute_status' => [
        'pending' => 'लंबित', 'active' => 'सक्रिय', 'suspended' => 'निलंबित',
        'expired' => 'समाप्त', 'closed' => 'बंद',
    ],
    'student_status' => [
        'active' => 'सक्रिय', 'completed' => 'पूर्ण', 'dropped' => 'छोड़ दिया', 'suspended' => 'निलंबित',
    ],
    'admission_source' => [
        'website' => 'वेबसाइट', 'walk_in' => 'सीधा प्रवेश', 'franchise' => 'फ्रैंचाइज़ी',
    ],
    'address_type' => [
        'office' => 'कार्यालय', 'current' => 'वर्तमान', 'permanent' => 'स्थायी',
    ],
    'document_type' => [
        'owner_aadhaar' => 'मालिक का आधार', 'owner_pan' => 'मालिक का पैन', 'gst_certificate' => 'जीएसटी प्रमाणपत्र',
        'rent_agreement' => 'किराया अनुबंध', 'center_photo' => 'केंद्र का फोटो', 'other' => 'अन्य',
    ],
    'verification_status' => [
        'pending' => 'लंबित', 'verified' => 'सत्यापित', 'rejected' => 'अस्वीकृत',
    ],
    'duration_unit' => [
        'hours' => 'घंटे', 'weeks' => 'सप्ताह', 'months' => 'महीने',
    ],
    'franchise_application_status' => [
        'new' => 'नया', 'contacted' => 'संपर्क किया', 'approved' => 'स्वीकृत', 'rejected' => 'अस्वीकृत',
    ],
    'wallet_transaction_type' => ['credit' => 'जमा', 'debit' => 'नामे'],
    'wallet_source' => ['recharge' => 'रिचार्ज', 'enrollment_fee' => 'नामांकन शुल्क', 'enrollment_refund' => 'नामांकन वापसी', 'adjustment' => 'समायोजन'],
    'recharge_status' => ['pending' => 'लंबित', 'approved' => 'स्वीकृत', 'rejected' => 'अस्वीकृत'],
    'payment_mode' => ['upi' => 'यूपीआई', 'bank_transfer' => 'बैंक ट्रांसफर', 'cash' => 'नकद', 'cheque' => 'चेक', 'card' => 'कार्ड'],
    'gender' => ['male' => 'पुरुष', 'female' => 'महिला', 'other' => 'अन्य'],
    'social_category' => ['gen' => 'सामान्य', 'obc' => 'अन्य पिछड़ा वर्ग', 'sc' => 'अनुसूचित जाति', 'st' => 'अनुसूचित जनजाति', 'ews' => 'आर्थिक रूप से कमजोर'],
    'guardian_relation' => ['father' => 'पिता', 'mother' => 'माता', 'guardian' => 'अभिभावक', 'spouse' => 'पति/पत्नी'],
    'qualification_level' => ['tenth' => 'हाईस्कूल', 'twelfth' => 'इंटरमीडिएट', 'diploma' => 'डिप्लोमा', 'graduation' => 'स्नातक', 'post_graduation' => 'परास्नातक', 'other' => 'अन्य'],
    'student_document_type' => ['photo' => 'फोटो', 'signature' => 'हस्ताक्षर', 'aadhaar' => 'आधार', 'tenth_marksheet' => 'हाईस्कूल अंकपत्र', 'twelfth_marksheet' => 'इंटरमीडिएट अंकपत्र', 'other' => 'अन्य'],
    'batch_status' => ['upcoming' => 'आगामी', 'running' => 'चालू', 'completed' => 'पूर्ण', 'cancelled' => 'रद्द'],
    'enrollment_status' => ['draft' => 'ड्राफ्ट', 'pending_approval' => 'मुख्यालय स्वीकृति लंबित', 'active' => 'सक्रिय', 'rejected' => 'अस्वीकृत', 'completed' => 'पूर्ण', 'dropped' => 'छोड़ दिया', 'cancelled' => 'रद्द'],
    'installment_status' => ['paid' => 'भुगतान हुआ', 'partial' => 'आंशिक भुगतान', 'overdue' => 'बकाया', 'upcoming' => 'आगामी'],
    'fee_payment_status' => ['valid' => 'मान्य', 'cancelled' => 'रद्द'],
    'attendance_status' => ['present' => 'उपस्थित', 'absent' => 'अनुपस्थित', 'late' => 'देर से', 'leave' => 'छुट्टी'],
];
