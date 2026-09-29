# KCTI Phase 6 — Public Website Implementation Plan

> **For agentic workers:** Execute inline with superpowers:executing-plans. Every task is test-first.

**Goal:** Kranti Computer Training Institute's public website with the reference project's look (indigo `#4f46e5` → violet `#7c3aed`, Plus Jakarta Sans, same section design), rebuilt on clean Blade components, fully bilingual (EN | हिं switcher), with a light/dark theme toggle, and every piece of content managed from the admin panel. Website forms feed the system: admission enquiries (main institute), contact enquiries, and franchise applications.

**Architecture:** `SiteController` renders pages from models (`Course`, `Slider`, `Notice`, `GalleryItem`, `FacultyMember`, `Testimonial`, `Download`) and `SettingsService` (cached key-value). `SetLocale` middleware reads the session (`/locale/{locale}` or `?lang=`). CSS is ported from `D:\Laravel\Kranti\public\css\main_site` into `public/css/site/` unchanged, plus `site-theme.css` that defines the dark palette under `[data-theme="dark"]`. Blade is split into a layout, partials (header, footer, login menu, switchers), and section components so each page is short.

**Spec:** §4.11, §8 (pages, language, theme), §6.1 step 1 and §6.4 (website forms), §12 row 6.

## Global Constraints

- Earlier phases' constraints apply.
- Reference colors/fonts/section styles are kept; content comes from the database or `lang/{en,hi}/site.php`.
- Every page renders in `en` and `hi`; translatable DB text uses `localized()` with English fallback.
- Theme: `data-theme` on `<html>`; initial value from `localStorage` (try/catch) else `prefers-color-scheme`; an inline `<head>` script applies it before paint.
- Forms: CSRF, server validation, `throttle:5,1`, honeypot field `website` must stay empty.
- Admission enquiries belong to the main institute; converting one creates the student there (`admission_source = website`).
- CMS images live on the `public` disk (`storage:link`).

## Review Focus

1. **A bot fills the honeypot or floods a form** → rejected/throttled, nothing stored.
2. **Switching language on any page** → same page in Hindi; missing Hindi text falls back to English, never blank.
3. **Inactive/hidden CMS items or courses with show_on_website off** → never shown.
4. **Converting the same enquiry twice** → one student.
5. **XSS through CMS or enquiry text shown in admin/site** → escaped.

---

### Task 1: Settings, locale, and theme infrastructure
Files: migration `settings`; model `Setting`; `SettingsService` (get/set/group, cached, typed defaults); admin page `SiteSettings` (site name/tagline, announcement + coupon, phones, email, address, map embed URL, socials, established year, stats numbers, payment details moving from `config/kcti.php`); `SetLocale` middleware (web group) + route `site.locale`; `lang/en/site.php`, `lang/hi/site.php`; tests `tests/Feature/Phase6/SettingsLocaleTest.php`.

### Task 2: CMS content
Files: migrations `sliders`, `notices`, `gallery_items`, `faculty_members`, `testimonials`, `downloads`; models (+ `scopeVisible`); admin resources under group "Website" using `LocalizedFields`; `Support` Shield keys for the support role; tests `tests/Feature/Phase6/CmsTest.php`.

### Task 3: Enquiries
Files: migration `enquiries`; enums `EnquiryType`, `EnquiryStatus`; `EnquiryService` (`submit`, `markContacted`, `close`, `convertToStudent` → StudentService with main institute + `AdmissionSource::Website`); admin resource with Convert action; tests `tests/Feature/Phase6/EnquiryTest.php`.

### Task 4: Website shell
Files: copy reference CSS/JS/images → `public/css/site`, `public/js/site`, `public/images`; `public/css/site/site-theme.css`; `resources/views/site/layouts/app.blade.php`, `partials/{header,footer,login-menu,locale-switch,theme-toggle}.blade.php`; `SiteController`; routes (§8.2); tests `tests/Feature/Phase6/WebsiteShellTest.php`.

### Task 5: Pages
Files: `site/pages/{home,about,courses/index,courses/show,placement,gallery,download,contact,admission,franchise/info,franchise/apply}.blade.php` + section components; verification pages moved into the layout; form handling (contact, admission, franchise apply) with honeypot; tests `tests/Feature/Phase6/PagesTest.php`, `tests/Feature/Phase6/FormsTest.php`.

### Task 6: Wrap-up
Files: README website section; installer seeds default settings and sample content-free site; tests for dark-mode script and hidden content.

Final: full suite, Pint, MariaDB fresh install + `php artisan serve` smoke, fresh-reviewer final review.
