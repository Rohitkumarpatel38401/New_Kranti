<?php

return [
    'institute_status' => [
        'pending' => 'Pending', 'active' => 'Active', 'suspended' => 'Suspended',
        'expired' => 'Expired', 'closed' => 'Closed',
    ],
    'student_status' => [
        'active' => 'Active', 'completed' => 'Completed', 'dropped' => 'Dropped', 'suspended' => 'Suspended',
    ],
    'admission_source' => [
        'website' => 'Website', 'walk_in' => 'Walk-in', 'franchise' => 'Franchise',
    ],
    'address_type' => [
        'office' => 'Office', 'current' => 'Current', 'permanent' => 'Permanent',
    ],
    'document_type' => [
        'owner_aadhaar' => 'Owner Aadhaar', 'owner_pan' => 'Owner PAN', 'gst_certificate' => 'GST Certificate',
        'rent_agreement' => 'Rent Agreement', 'center_photo' => 'Centre Photo', 'other' => 'Other',
    ],
    'verification_status' => [
        'pending' => 'Pending', 'verified' => 'Verified', 'rejected' => 'Rejected',
    ],
    'duration_unit' => [
        'hours' => 'Hours', 'weeks' => 'Weeks', 'months' => 'Months',
    ],
    'franchise_application_status' => [
        'new' => 'New', 'contacted' => 'Contacted', 'approved' => 'Approved', 'rejected' => 'Rejected',
    ],
    'wallet_transaction_type' => ['credit' => 'Credit', 'debit' => 'Debit'],
    'wallet_source' => ['recharge' => 'Recharge', 'enrollment_fee' => 'Enrollment fee', 'enrollment_refund' => 'Enrollment refund', 'adjustment' => 'Adjustment'],
    'recharge_status' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'],
    'payment_mode' => ['upi' => 'UPI', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'cheque' => 'Cheque', 'card' => 'Card'],
];
