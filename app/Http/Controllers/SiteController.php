<?php

namespace App\Http\Controllers;

use App\Enums\EnquiryType;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Download;
use App\Models\FacultyMember;
use App\Models\GalleryItem;
use App\Models\Notice;
use App\Models\Slider;
use App\Models\Testimonial;
use App\Services\EnquiryService;
use App\Services\FranchiseOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Public website pages. Content comes from the admin CMS and site settings ($site is shared by a view composer).
 * Forms are CSRF-protected, throttled (site-forms) and carry a honeypot field named "website".
 */
class SiteController extends Controller
{
    private const PHONE = ['required', 'string', 'max:20', 'regex:/^\+?[0-9][0-9 \-]{8,15}$/'];

    public function home(): View
    {
        $featured = Course::query()->onWebsite()->with('category')->where('is_featured', true)->limit(6)->get();

        return view('site.pages.home', [
            'sliders' => Slider::query()->visible()->get(),
            'courses' => $featured->isNotEmpty() ? $featured : Course::query()->onWebsite()->with('category')->limit(6)->get(),
            'testimonials' => Testimonial::query()->visible()->limit(8)->get(),
            'faculty' => FacultyMember::query()->visible()->limit(6)->get(),
            'gallery' => GalleryItem::query()->visible()->limit(5)->get(),
            'notices' => Notice::query()->visible()->limit(8)->get(),
        ]);
    }

    public function about(): View
    {
        return view('site.pages.about', ['faculty' => FacultyMember::query()->visible()->get()]);
    }

    public function courses(Request $request): View
    {
        $categories = CourseCategory::query()
            ->where('is_active', true)
            ->whereHas('courses', fn ($q) => $q->onWebsite())
            ->orderBy('sort_order')->orderBy('id')
            ->get();

        $active = $categories->firstWhere('slug', $request->query('category'));

        return view('site.pages.courses.index', [
            'categories' => $categories,
            'active' => $active,
            'courses' => Course::query()->onWebsite()->with('category')
                ->when($active, fn ($q) => $q->where('course_category_id', $active->id))
                ->get(),
        ]);
    }

    public function course(string $slug): View
    {
        $course = Course::query()->onWebsite()->where('slug', $slug)
            ->with(['category', 'courseSubjects.subject'])
            ->firstOrFail();

        return view('site.pages.courses.show', ['course' => $course]);
    }

    public function placement(): View
    {
        return view('site.pages.placement', ['testimonials' => Testimonial::query()->visible()->get()]);
    }

    public function gallery(): View
    {
        return view('site.pages.gallery', ['items' => GalleryItem::query()->visible()->get()]);
    }

    public function downloads(): View
    {
        return view('site.pages.download', ['downloads' => Download::query()->visible()->get()]);
    }

    public function contact(): View
    {
        return view('site.pages.contact');
    }

    public function registration(Request $request): View
    {
        $courses = Course::query()->onWebsite()->get();

        return view('site.pages.registration', [
            'courses' => $courses,
            'selected' => $courses->firstWhere('id', (int) $request->query('course'))?->id,
        ]);
    }

    public function franchiseInfo(): View
    {
        return view('site.pages.franchise.info');
    }

    public function franchiseApply(): View
    {
        return view('site.pages.franchise.apply');
    }

    public function submitContact(Request $request, EnquiryService $enquiries): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => self::PHONE,
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        if (! $this->isBot($request)) {
            $enquiries->submit([...$data, 'type' => EnquiryType::Contact]);
        }

        return redirect()->route('site.contact')->with('status', __('site.contact.sent'));
    }

    public function submitRegistration(Request $request, EnquiryService $enquiries): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => self::PHONE,
            'email' => ['nullable', 'email', 'max:255'],
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')->where('is_active', true)->where('show_on_website', true)->whereNull('deleted_at')],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $this->isBot($request)) {
            $enquiries->submit([...$data, 'type' => EnquiryType::Admission]);
        }

        return redirect()->route('site.students.registration')->with('status', __('site.registration.sent'));
    }

    public function submitFranchise(Request $request, FranchiseOnboardingService $franchise): RedirectResponse
    {
        $data = $request->validate([
            'applicant_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => self::PHONE,
            'proposed_center_name' => ['required', 'string', 'max:200'],
            'computers_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'area_sqft' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:60'],
            'message' => ['nullable', 'string', 'max:2000'],
            'line_1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'district' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['required', 'digits:6'],
        ]);

        if (! $this->isBot($request)) {
            $address = collect($data)->only(['line_1', 'city', 'district', 'state', 'pincode'])->all();
            $application = collect($data)->except(array_keys($address))->all();
            $application['phone'] = preg_replace('/\D+/', '', $application['phone']);

            $franchise->submit($application, $address);
        }

        return redirect()->route('site.franchise.apply')->with('status', __('site.franchise.sent'));
    }

    /** The hidden "website" field is invisible to people; a filled one means a bot. Bots get the same thank-you page. */
    private function isBot(Request $request): bool
    {
        return filled($request->input('website'));
    }
}
