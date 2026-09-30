<?php

namespace Database\Seeders;

use App\Enums\DurationUnit;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Subject;
use Illuminate\Database\Seeder;

/**
 * Demo course catalogue: categories, subjects and courses with marks, in English and Hindi.
 * Keyed by slug/code, so it never duplicates or overwrites what HQ has edited.
 */
class DemoCatalogSeeder extends Seeder
{
    private const CATEGORIES = [
        'diploma' => ['Diploma Courses', 'डिप्लोमा कोर्स'],
        'certificate' => ['Certificate Courses', 'सर्टिफिकेट कोर्स'],
        'accounting' => ['Accounting', 'अकाउंटिंग'],
        'programming' => ['Programming & Web', 'प्रोग्रामिंग और वेब'],
    ];

    /** code => [en, hi] */
    private const SUBJECTS = [
        'FUND' => ['Computer Fundamentals', 'कंप्यूटर के मूल सिद्धांत'],
        'MSO' => ['MS Office (Word, Excel, PowerPoint)', 'एमएस ऑफिस (वर्ड, एक्सेल, पावरपॉइंट)'],
        'INET' => ['Internet & Email', 'इंटरनेट और ईमेल'],
        'DTP' => ['Desktop Publishing', 'डेस्कटॉप पब्लिशिंग'],
        'TALLY' => ['Tally Prime', 'टैली प्राइम'],
        'GST' => ['GST & Taxation', 'जीएसटी और कराधान'],
        'HTML' => ['HTML & CSS', 'एचटीएमएल और सीएसएस'],
        'JS' => ['JavaScript', 'जावास्क्रिप्ट'],
        'PY' => ['Python Programming', 'पायथन प्रोग्रामिंग'],
        'TYPE' => ['Typing (English & Hindi)', 'टाइपिंग (अंग्रेज़ी और हिंदी)'],
        'DBMS' => ['Database Concepts', 'डेटाबेस की अवधारणाएँ'],
    ];

    /** code => [category, en, hi, months, suggested fee, HQ fee, featured, short en, short hi, subjects] */
    private const COURSES = [
        'DCA' => ['diploma', 'Diploma in Computer Applications', 'कंप्यूटर एप्लीकेशन में डिप्लोमा', 6, 9000, 600, true,
            'Office, internet and DTP skills for any job.', 'किसी भी नौकरी के लिए ऑफिस, इंटरनेट और डीटीपी कौशल।', ['FUND', 'MSO', 'INET', 'DTP']],
        'ADCA' => ['diploma', 'Advanced Diploma in Computer Applications', 'कंप्यूटर एप्लीकेशन में एडवांस डिप्लोमा', 12, 15000, 900, true,
            'A complete one-year programme: office, accounts and web basics.', 'एक साल का पूरा कोर्स: ऑफिस, अकाउंट्स और वेब की बुनियाद।', ['FUND', 'MSO', 'INET', 'TALLY', 'HTML']],
        'PGDCA' => ['diploma', 'Post Graduate Diploma in Computer Applications', 'कंप्यूटर एप्लीकेशन में पोस्ट ग्रेजुएट डिप्लोमा', 12, 18000, 1000, false,
            'For graduates: programming, databases and applications.', 'स्नातकों के लिए: प्रोग्रामिंग, डेटाबेस और एप्लीकेशन।', ['FUND', 'DBMS', 'PY', 'HTML']],
        'CCC' => ['certificate', 'Course on Computer Concepts', 'कंप्यूटर कॉन्सेप्ट्स कोर्स', 3, 3000, 300, true,
            'The basic computer course for government jobs.', 'सरकारी नौकरियों के लिए बुनियादी कंप्यूटर कोर्स।', ['FUND', 'INET']],
        'OLEVEL' => ['certificate', 'O Level Preparation', 'ओ लेवल तैयारी', 12, 14000, 800, false,
            'Complete preparation for the NIELIT O Level exams.', 'NIELIT ओ लेवल परीक्षा की पूरी तैयारी।', ['FUND', 'HTML', 'PY', 'INET']],
        'DEO' => ['certificate', 'Data Entry Operator', 'डेटा एंट्री ऑपरेटर', 3, 3500, 300, false,
            'Fast typing and Excel for data entry jobs.', 'डेटा एंट्री नौकरियों के लिए तेज़ टाइपिंग और एक्सेल।', ['TYPE', 'MSO']],
        'TALLY' => ['accounting', 'Tally Prime with GST', 'जीएसटी के साथ टैली प्राइम', 3, 5000, 400, true,
            'Business accounting, GST and payroll.', 'व्यापार अकाउंटिंग, जीएसटी और पेरोल।', ['TALLY', 'GST']],
        'WEB' => ['programming', 'Web Design & Development', 'वेब डिज़ाइन और डेवलपमेंट', 4, 8000, 600, true,
            'Build professional websites with HTML, CSS and JavaScript.', 'एचटीएमएल, सीएसएस और जावास्क्रिप्ट से प्रोफेशनल वेबसाइट बनाएँ।', ['HTML', 'JS']],
        'PYTHON' => ['programming', 'Python Programming', 'पायथन प्रोग्रामिंग', 3, 6000, 500, false,
            'Programming from scratch with Python.', 'पायथन के साथ शुरू से प्रोग्रामिंग।', ['PY', 'DBMS']],
    ];

    public function run(): void
    {
        $categories = [];
        $order = 0;

        foreach (self::CATEGORIES as $slug => [$en, $hi]) {
            $categories[$slug] = CourseCategory::withTrashed()->firstOrCreate(
                ['slug' => $slug],
                ['name' => ['en' => $en, 'hi' => $hi], 'sort_order' => ++$order, 'is_active' => true],
            );
        }

        $subjects = [];

        foreach (self::SUBJECTS as $code => [$en, $hi]) {
            $subjects[$code] = Subject::withTrashed()->firstOrCreate(['code' => $code], ['name' => ['en' => $en, 'hi' => $hi], 'is_active' => true]);
        }

        $order = 0;

        foreach (self::COURSES as $code => [$category, $en, $hi, $months, $fee, $hqFee, $featured, $shortEn, $shortHi, $subjectCodes]) {
            $course = Course::withTrashed()->where('code', $code)->first();
            $order++;

            if ($course) {
                continue;
            }

            $course = Course::create([
                'course_category_id' => $categories[$category]->id,
                'code' => $code,
                'name' => ['en' => $en, 'hi' => $hi],
                'slug' => strtolower($code),
                'short_description' => ['en' => $shortEn, 'hi' => $shortHi],
                'description' => [
                    'en' => "{$en} ({$code}) is a {$months}-month course with classroom teaching and daily practical lab sessions.\n\nEvery student gets study material, regular tests and a KCTI certificate that can be verified online by QR code.",
                    'hi' => "{$hi} ({$code}) {$months} महीने का कोर्स है जिसमें कक्षा शिक्षण और रोज़ाना प्रैक्टिकल लैब शामिल है।\n\nहर छात्र को अध्ययन सामग्री, नियमित टेस्ट और QR कोड से ऑनलाइन सत्यापित होने वाला KCTI प्रमाणपत्र मिलता है।",
                ],
                'duration_value' => $months,
                'duration_unit' => DurationUnit::Months,
                'total_hours' => $months * 40,
                'eligibility' => ['en' => '10th pass or above', 'hi' => '10वीं पास या उससे ऊपर'],
                'hq_registration_fee' => $hqFee,
                'suggested_fee' => $fee,
                'is_featured' => $featured,
                'show_on_website' => true,
                'is_active' => true,
                'sort_order' => $order,
            ]);

            foreach (array_values($subjectCodes) as $i => $subjectCode) {
                $course->courseSubjects()->create([
                    'subject_id' => $subjects[$subjectCode]->id,
                    'term_no' => 1,
                    'sort_order' => $i + 1,
                    'theory_max' => 70,
                    'practical_max' => 30,
                    'pass_marks' => 33,
                ]);
            }
        }
    }
}
