<?php

namespace Database\Seeders;

use App\Models\AcademicSession;
use App\Models\GradeScale;
use Illuminate\Database\Seeder;

/**
 * Essential academic data: a current session (April–March) and the standard grade scale.
 * Safe to re-run; never overrides what HQ has already configured.
 */
class AcademicDefaultsSeeder extends Seeder
{
    public const GRADES = [
        ['A+', 90, 100, 'Outstanding', 'उत्कृष्ट'],
        ['A', 75, 89.99, 'Excellent', 'श्रेष्ठ'],
        ['B', 60, 74.99, 'Very Good', 'बहुत अच्छा'],
        ['C', 45, 59.99, 'Good', 'अच्छा'],
        ['D', 33, 44.99, 'Pass', 'उत्तीर्ण'],
        ['F', 0, 32.99, 'Fail', 'अनुत्तीर्ण'],
    ];

    public function run(): void
    {
        if (GradeScale::query()->doesntExist()) {
            foreach (self::GRADES as $i => [$grade, $min, $max, $en, $hi]) {
                GradeScale::create([
                    'grade' => $grade,
                    'min_percent' => $min,
                    'max_percent' => $max,
                    'remark' => ['en' => $en, 'hi' => $hi],
                    'sort_order' => $i,
                ]);
            }
        }

        if (AcademicSession::query()->where('is_current', true)->doesntExist()) {
            $start = now()->month >= 4 ? now()->year : now()->year - 1;
            $code = $start.'-'.substr((string) ($start + 1), 2);

            AcademicSession::query()->updateOrCreate(['code' => $code], [
                'name' => $code,
                'starts_on' => "{$start}-04-01",
                'ends_on' => ($start + 1).'-03-31',
                'is_current' => true,
            ]);
        }
    }
}
