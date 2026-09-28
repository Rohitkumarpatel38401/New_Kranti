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
];
