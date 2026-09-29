<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\FacultyMember;
use App\Models\GalleryItem;
use App\Models\Notice;
use App\Models\Slider;
use App\Models\Testimonial;
use Illuminate\Contracts\View\View;

/**
 * Public website pages. Content comes from the admin CMS and site settings ($site is shared by a view composer).
 */
class SiteController extends Controller
{
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
}
