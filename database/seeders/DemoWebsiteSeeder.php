<?php

namespace Database\Seeders;

use App\Models\Download;
use App\Models\FacultyMember;
use App\Models\GalleryItem;
use App\Models\Notice;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\Testimonial;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Demo website content: settings, sliders, notices, gallery, faculty, alumni and downloads.
 * Each content type is only filled while its table is empty, and settings only when never saved,
 * so running it again never duplicates or overwrites what HQ has entered.
 */
class DemoWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $this->sliders();
        $this->notices();
        $this->gallery();
        $this->faculty();
        $this->testimonials();
        $this->downloads();
    }

    private function settings(): void
    {
        $values = [
            'site.phone' => '9026043415',
            'site.phone_alt' => '8546033850',
            'site.email' => 'info@kranticomputer.example',
            'site.address' => "Kranti Computer, Near Main Chauraha,\nHandia, Prayagraj 221503",
            'site.announcement' => ['en' => '🎓 New batches start every Monday! Use code', 'hi' => '🎓 हर सोमवार नए बैच शुरू! कोड इस्तेमाल करें'],
            'site.coupon' => 'KRANTI30',
            'site.about' => [
                'en' => "Established in 2016, Kranti Computer Training Institute (KCTI) is a leading computer education provider. We run job-oriented courses through our own centre and a growing network of authorised study centres.\n\nExams, results and certificates are managed centrally by our head office, and every certificate can be verified online.",
                'hi' => "2016 में स्थापित क्रांति कंप्यूटर ट्रेनिंग इंस्टीट्यूट (KCTI) एक अग्रणी कंप्यूटर शिक्षा संस्थान है। हम अपने केंद्र और अधिकृत अध्ययन केंद्रों के बढ़ते नेटवर्क के माध्यम से रोज़गारपरक कोर्स चलाते हैं।\n\nपरीक्षा, परिणाम और प्रमाणपत्र मुख्यालय द्वारा संचालित होते हैं, और हर प्रमाणपत्र ऑनलाइन सत्यापित किया जा सकता है।",
            ],
            'site.socials' => ['facebook' => 'https://facebook.com/', 'youtube' => 'https://youtube.com/', 'instagram' => 'https://instagram.com/'],
            'site.online_courses_url' => 'https://youtube.com/',
            'site.map_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d57665.1!2d82.18!3d25.36!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zSGFuZGlh!5e0!3m2!1sen!2sin!4v1',
        ];

        $settings = app(SettingsService::class);

        foreach ($values as $key => $value) {
            [$group, $name] = explode('.', $key, 2);

            if (Setting::query()->where('group', $group)->where('key', $name)->doesntExist()) {
                $settings->set($key, $value);
            }
        }
    }

    private function sliders(): void
    {
        if (Slider::query()->exists()) {
            return;
        }

        $slides = [
            [['en' => '🎓 Admissions Open 2026', 'hi' => '🎓 प्रवेश जारी 2026'], ['en' => 'Learn . Build . Succeed', 'hi' => 'सीखें . बनाएं . आगे बढ़ें'],
                ['en' => 'The trusted name in computer education. Industry-ready courses that lead to jobs.', 'hi' => 'कंप्यूटर शिक्षा का भरोसेमंद नाम। नौकरी दिलाने वाले कोर्स।'], '/students/registration'],
            [['en' => '💼 Placement Support', 'hi' => '💼 प्लेसमेंट सहायता'], ['en' => 'Job-Ready Skills', 'hi' => 'नौकरी के लिए तैयार कौशल'],
                ['en' => 'From resume building to interview preparation — we are with you at every step.', 'hi' => 'रिज़्यूमे से इंटरव्यू तक — हर कदम पर हम आपके साथ हैं।'], '/placement'],
            [['en' => '🏫 Open a Study Centre', 'hi' => '🏫 अध्ययन केंद्र खोलें'], ['en' => 'Become a KCTI Franchise', 'hi' => 'KCTI फ्रैंचाइज़ी बनें'],
                ['en' => 'Ready courses, online management and verified certificates.', 'hi' => 'तैयार कोर्स, ऑनलाइन प्रबंधन और सत्यापित प्रमाणपत्र।'], '/franchise/info'],
        ];

        foreach ($slides as $i => [$label, $title, $subtitle, $url]) {
            Slider::create([
                'label' => $label, 'title' => $title, 'subtitle' => $subtitle,
                'button_text' => ['en' => 'Know more →', 'hi' => 'और जानें →'], 'button_url' => $url,
                'sort_order' => $i + 1, 'is_active' => true,
            ]);
        }
    }

    private function notices(): void
    {
        if (Notice::query()->withTrashed()->exists()) {
            return;
        }

        $notices = [
            [['en' => 'O Level new batch — 11 AM & 4 PM', 'hi' => 'ओ लेवल नया बैच — सुबह 11 और शाम 4 बजे'], 'hot', true, 1],
            [['en' => 'CCC exam form dates announced', 'hi' => 'CCC परीक्षा फ़ॉर्म की तिथियाँ घोषित'], 'new', true, 3],
            [['en' => 'Tally Prime with GST weekend batch', 'hi' => 'जीएसटी के साथ टैली प्राइम वीकेंड बैच'], null, true, 6],
            [['en' => 'Results of the September exams published', 'hi' => 'सितंबर परीक्षा के परिणाम प्रकाशित'], null, false, 10],
            [['en' => 'Holiday on account of Diwali', 'hi' => 'दिवाली के कारण अवकाश'], null, false, 20],
        ];

        foreach ($notices as [$title, $badge, $ticker, $daysAgo]) {
            Notice::create([
                'title' => $title,
                'body' => ['en' => 'Contact the office or call us for details.', 'hi' => 'अधिक जानकारी के लिए कार्यालय से संपर्क करें या कॉल करें।'],
                'badge' => $badge, 'show_in_ticker' => $ticker, 'is_active' => true,
                'published_at' => now()->subDays($daysAgo),
            ]);
        }
    }

    private function gallery(): void
    {
        if (GalleryItem::query()->exists()) {
            return;
        }

        $items = [
            ['gallery/center.jpeg', ['en' => 'Our centre', 'hi' => 'हमारा केंद्र'], 'campus'],
            ['photo.jpg', ['en' => 'Computer lab', 'hi' => 'कंप्यूटर लैब'], 'lab'],
            ['sumit.jpeg', ['en' => 'Practical class', 'hi' => 'प्रैक्टिकल कक्षा'], 'lab'],
            ['teacher/himanshu.jpeg', ['en' => 'Faculty at work', 'hi' => 'कार्यरत शिक्षक'], 'events'],
            ['teacher/jaypujan.jpeg', ['en' => 'Workshop day', 'hi' => 'वर्कशॉप दिवस'], 'events'],
        ];

        foreach ($items as $i => [$file, $title, $category]) {
            if ($path = $this->copyImage($file, 'gallery')) {
                GalleryItem::create(['title' => $title, 'category' => $category, 'image_path' => $path, 'sort_order' => $i + 1, 'is_active' => true]);
            }
        }
    }

    private function faculty(): void
    {
        if (FacultyMember::query()->exists()) {
            return;
        }

        $members = [
            ['Mr. Hanumat Singh', ['en' => 'Computer Hardware & Software', 'hi' => 'कंप्यूटर हार्डवेयर और सॉफ़्टवेयर'], 'MCA', 10, 'teacher/himanshu.jpeg'],
            ['Mr. Shubham Sir', ['en' => 'Web Design & Languages', 'hi' => 'वेब डिज़ाइन और भाषाएँ'], 'B.Tech', 6, 'teacher/jaypujan.jpeg'],
            ['Mr. Suvesh Singh', ['en' => 'Accounting & Tally', 'hi' => 'अकाउंटिंग और टैली'], 'M.Com', 5, null],
        ];

        foreach ($members as $i => [$name, $designation, $qualification, $years, $photo]) {
            FacultyMember::create([
                'name' => $name, 'designation' => $designation, 'qualification' => $qualification, 'experience_years' => $years,
                'photo_path' => $photo ? $this->copyImage($photo, 'faculty') : null, 'sort_order' => $i + 1, 'is_active' => true,
            ]);
        }
    }

    private function testimonials(): void
    {
        if (Testimonial::query()->exists()) {
            return;
        }

        $people = [
            ['Jaypujan Prajapati', 'ADCA', 'TCS', 'Software Developer', 'photo.jpg'],
            ['Sumit Kumar', 'DCA', 'SBI Branch', 'Data Entry Operator', 'sumit.jpeg'],
            ['Rohit Patel', 'Web Design', 'Freelance', 'Web Designer', 'photo.jpg'],
            ['Pooja Yadav', 'Tally Prime', 'Yadav Traders', 'Accountant', 'sumit.jpeg'],
        ];

        foreach ($people as $i => [$name, $course, $company, $position, $photo]) {
            Testimonial::create([
                'name' => $name, 'course_name' => $course, 'company' => $company, 'position' => $position,
                'photo_path' => $this->copyImage($photo, 'testimonials'),
                'quote' => ['en' => 'The practical classes and teachers at Kranti helped me get my first job.', 'hi' => 'क्रांति की प्रैक्टिकल कक्षाओं और शिक्षकों ने मुझे पहली नौकरी दिलाने में मदद की।'],
                'sort_order' => $i + 1, 'is_active' => true,
            ]);
        }
    }

    private function downloads(): void
    {
        if (Download::query()->exists()) {
            return;
        }

        foreach ([['admission-form', ['en' => 'Admission form', 'hi' => 'प्रवेश फ़ॉर्म'], 'forms'], ['syllabus-dca', ['en' => 'DCA syllabus', 'hi' => 'DCA पाठ्यक्रम'], 'syllabus']] as $i => [$name, $title, $category]) {
            $path = "downloads/{$name}.pdf";
            Storage::disk('public')->put($path, $this->pdf($title['en']));
            Download::create(['title' => $title, 'category' => $category, 'disk' => 'public', 'path' => $path, 'sort_order' => $i + 1, 'is_active' => true]);
        }
    }

    /** Copies a bundled reference image (public/images/site) to the public disk. */
    private function copyImage(string $file, string $directory): ?string
    {
        $source = public_path('images/site/'.$file);

        if (! is_file($source)) {
            return null;
        }

        $path = $directory.'/demo-'.str_replace('/', '-', $file);
        Storage::disk('public')->put($path, file_get_contents($source));

        return $path;
    }

    /** A tiny one-page PDF so the demo download links work. */
    private function pdf(string $text): string
    {
        $content = "BT /F1 24 Tf 72 720 Td ({$text}) Tj ET";

        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            .'4 0 obj<</Length '.strlen($content).">>stream\n{$content}\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
    }
}
