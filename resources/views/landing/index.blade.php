@extends('layouts.landing')

@section('title', 'Conscious Connections — Safe, Honest Sex Education for Filipino Learners')
@section('meta_description', 'A safe and judgment-free space for Filipino youth to learn about sexual health, relationships, and well-being with confidence.')

@section('content')

<!----Navbar---->
<nav
    x-data="{
        scrolled: false,
        mobileOpen: false,
        activeSection: 'for-who',
        sectionIds: ['edu-section', 'for-who', 'features', 'vision', 'download-app'],
        init() {
            const sections = this.sectionIds
                .map(id => document.getElementById(id))
                .filter(Boolean);

            const setFromHash = () => {
                const hashId = window.location.hash.replace('#', '');
                if (this.sectionIds.includes(hashId)) {
                    this.activeSection = hashId;
                }
            };

            setFromHash();
            window.addEventListener('hashchange', setFromHash);

            if (!sections.length) {
                return;
            }

            const sectionObserver = new IntersectionObserver((entries) => {
                const visibleEntries = entries
                    .filter(entry => entry.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

                if (visibleEntries.length > 0) {
                    this.activeSection = visibleEntries[0].target.id;
                }
            }, {
                rootMargin: '-35% 0px -50% 0px',
                threshold: [0.15, 0.35, 0.6],
            });

            sections.forEach(section => sectionObserver.observe(section));
        }
    }"
    @scroll.window="scrolled = window.scrollY > 60"
    :class="scrolled
        ? 'nav-scrolled bg-white/92 backdrop-blur-md shadow-sm border-b border-purple-100/60'
        : 'bg-transparent'"
    class="fixed top-0 left-0 right-0 z-50 transition-all duration-400">

    <div class="max-w-7xl mt-5 mx-auto px-6 lg:px-8">
        <div class="flex items-center justify-between h-[80px] mb-4">

            {{-- Logo: sign.png + two-line brand name --}}
            <a href="{{ route('home') }}" class="flex items-center gap-3.5 flex-shrink-0 group">
                <div class="nav-logo-mark relative">
                    <img src="{{ asset('landing/sign.png') }}"
                        alt="Conscious Connections"
                         class="h-12 w-auto object-contain relative z-10 transition-transform duration-300 group-hover:scale-110">
                    <div class="nav-logo-glow absolute inset-0 rounded-full blur-md opacity-0 group-hover:opacity-60 transition-opacity duration-300"
                         style="background: radial-gradient(circle, rgba(163,14,178,0.5), transparent 70%);"></div>
                </div>
                <div :class="scrolled ? 'nav-brand-scrolled' : ''" class="nav-brand-text flex flex-col leading-none gap-0.5">
                    <span class="nav-brand-name block text-[20px] font-black tracking-[0.06em] leading-none transition-all duration-300"
                          :class="scrolled ? '' : 'text-white'">
                       Conscious Connections
                    </span>
                </div>
            </a>

            {{-- Desktop nav: links + CTA grouped on right (BrightMind layout) --}}
            <div class="hidden lg:flex items-center gap-10">
                <ul class="flex items-center gap-8">
                    @foreach ([
                        ['id' => 'for-who', 'href' => '#for-who', 'label' => "Who It's For"],
                        ['id' => 'features', 'href' => '#features', 'label' => 'Features'],
                        ['id' => 'vision', 'href' => '#vision', 'label' => 'About'],
                        ['id' => 'download-app', 'href' => '#download-app', 'label' => 'Get the App'],
                    ] as $item)
                    <li>
                        <a href="{{ $item['href'] }}"
                           @click="activeSection = '{{ $item['id'] }}'"
                           :class="[
                               scrolled ? 'text-gray-600 hover:text-purple-700' : 'text-white/85 hover:text-white',
                               activeSection === '{{ $item['id'] }}' ? (scrolled ? 'text-purple-700' : 'text-white') : ''
                           ]"
                           :aria-current="activeSection === '{{ $item['id'] }}' ? 'page' : null"
                           :data-active="activeSection === '{{ $item['id'] }}'"
                           class="nav-link text-[14px] font-medium transition-colors duration-200">
                            {{ $item['label'] }}
                        </a>
                    </li>
                    @endforeach
                </ul>
                <div class="flex items-center gap-3">
                    <a href="{{ route('login') }}"
                       :class="scrolled
                           ? 'nav-login-scrolled text-gray-700 border-gray-300'
                           : 'text-white border-white/40'"
                       class="nav-login-btn relative overflow-hidden px-5 py-2.5 rounded-full border font-semibold text-[14px] transition-all duration-200">
                        <span class="relative z-10">Log In</span>
                    </a>
                    <a href="{{ route('register') }}"
                       class="nav-cta-btn lp-btn-gradient lp-changing-gradient relative overflow-hidden px-5 py-2.5 rounded-full font-semibold text-[14px] text-white">
                        <span class="relative z-10">Get Started</span>
                    </a>
                </div>
            </div>

            {{-- Mobile hamburger --}}
            <button @click="mobileOpen = !mobileOpen"
                    :class="scrolled ? 'text-gray-700 hover:bg-gray-100' : 'text-white hover:bg-white/10'"
                    class="lg:hidden p-2 rounded-lg transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path x-show="!mobileOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path x-show="mobileOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Mobile menu --}}
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             :class="scrolled ? 'border-purple-100' : 'border-white/20 backdrop-blur-md bg-purple-900/95'"
             class="lg:hidden pb-4 border-t mt-1">
            <nav class="flex flex-col gap-1 pt-3">
                @foreach ([
                    ['id' => 'edu-section', 'href' => '#edu-section', 'label' => "What You'll Learn"],
                    ['id' => 'for-who', 'href' => '#for-who', 'label' => "Who It's For"],
                    ['id' => 'features', 'href' => '#features', 'label' => 'Features'],
                    ['id' => 'vision', 'href' => '#vision', 'label' => 'About'],
                    ['id' => 'download-app', 'href' => '#download-app', 'label' => 'Get the App'],
                ] as $item)
                <a href="{{ $item['href'] }}" @click="mobileOpen = false; activeSection = '{{ $item['id'] }}'"
                   :class="[
                       scrolled ? 'text-gray-700 hover:bg-purple-50' : 'text-white hover:bg-white/10',
                       activeSection === '{{ $item['id'] }}' ? (scrolled ? 'bg-purple-50 text-purple-700' : 'bg-white/10 text-white') : ''
                   ]"
                   :aria-current="activeSection === '{{ $item['id'] }}' ? 'page' : null"
                   class="px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">
                    {{ $item['label'] }}
                </a>
                @endforeach
                <div class="flex gap-2 px-4 pt-3 mt-1 border-t"
                     :class="scrolled ? 'border-purple-100' : 'border-white/20'">
                    <a href="{{ route('login') }}"
                       :class="scrolled ? 'text-purple-700 border-purple-300' : 'text-white border-white/40'"
                       class="flex-1 text-center px-4 py-2.5 rounded-full border text-sm font-semibold">
                        Log In
                    </a>
                    <a href="{{ route('register') }}"
                       class="lp-btn-gradient lp-changing-gradient flex-1 text-center px-4 py-2.5 rounded-full text-sm font-semibold text-white">
                        Get Started
                    </a>
                </div>
            </nav>
        </div>
    </div>
</nav>


{{-- ═══════════════════════════════════════════════════
     HERO HEADER
     - Animated gradient background
     - 7 floating orbs
     - Mixed solid/outline heading with letter-expand
     - Gradient rule + subtitle
═══════════════════════════════════════════════════ --}}
<header class="lp-header lp-changing-gradient">

    {{-- 7 Floating orbs --}}
    <div class="lp-orb lp-orb-1"></div>
    <div class="lp-orb lp-orb-2"></div>
    <div class="lp-orb lp-orb-3"></div>
    <div class="lp-orb lp-orb-4"></div>
    <div class="lp-orb lp-orb-5"></div>
    <div class="lp-orb lp-orb-6"></div>
    <div class="lp-orb lp-orb-7"></div>

    {{-- Heading --}}
    <div class="lp-head">
        {{-- Ambient glow behind text --}}
        <div class="lp-head-glow"></div>

        <h1 class="lp-hero-title"> 
            <span class="lp-title-line1">CONSCIOUS</span>
            <span class="lp-title-line1">CONNECTIONS</span>
        </h1>

        {{-- Decorative animated rule --}}
        <div class="lp-title-rule"></div>

        <p class="lp-hero-sub">Safe, honest sex education for every Filipino learner.</p>
    </div>

    {{-- Wave to white START section --}}
    <svg class="lp-wave" viewBox="0 0 1440 120" preserveAspectRatio="none">
        <path fill="#ffffff" d="M0,64L80,58.7C160,53,320,43,480,53.3C640,64,800,96,960,96C1120,96,1280,64,1360,48L1440,32L1440,120L1360,120C1280,120,1120,120,960,120C800,120,640,120,480,120C320,120,160,120,80,120L0,120Z"/>
    </svg>
</header>


{{-- ═══════════════════════════════════════════════════
     START LEARNING
     - books.png floating left (enhanced)
     - sign.png grounded right with glow platform
     - panFadeLeft text animations
═══════════════════════════════════════════════════ --}}
<section class="lp-start-section">
    <div class="lp-sign">

        {{-- Heading text --}}
        <h1 class="lp-start">START</h1>
        <h1 class="lp-learning">LEARNING</h1>

        {{-- CTA button --}}
        <div style="position: relative; z-index: 2; margin-top: 36px;">
            <a href="{{ route('register') }}"
               class="lp-start-cta-btn lp-btn-gradient-strong lp-changing-gradient relative overflow-hidden inline-flex items-center gap-2.5 px-8 py-3.5 rounded-full text-white font-semibold text-[15px]">
                <span>Begin Your Journey</span>
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                </svg>
            </a>
        </div>

        {{-- sign.png — grounded right with platform glow --}}
        <div class="lp-sign-wrap">
            <div class="lp-sign-platform"></div>
            <img src="{{ asset('landing/sign.png') }}"
                  alt="Conscious Connections"
                 class="lp-hero-image">
        </div>


    </div>
</section>


{{-- ═══════════════════════════════════════════════════
     EDU SECTION — 6 Topic Category Icons
     - White background
     - Section badge + h2 + description
     - 6-column icon grid (2→3→6 responsive)
     - scroll-reveal, hover lift
═══════════════════════════════════════════════════ --}}
<section id="edu-section" class="py-20 lg:py-24 bg-white">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">

        <div class="text-center mb-14 scroll-reveal">
            <div class="lp-section-badge inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full mb-4 text-xs font-bold tracking-widest uppercase">
                What You'll Learn
            </div>
            <h2 class="lp-section-title text-3xl lg:text-4xl font-bold mb-3">
                Sex Education Learning Platform
            </h2>
            <p class="text-gray-500 text-base max-w-3xl mx-auto leading-relaxed">
                A safe and welcoming space where young people can learn about their bodies, relationships,
                and well-being with confidence — judgment-free sex education that supports curiosity,
                encourages healthy choices, and helps everyone feel informed and empowered.
            </p>
        </div>

        @php
        $eduCategories = [
            ['icon' => 'user.png',        'label' => 'Anatomy'],
            ['icon' => 'relationship.png', 'label' => 'Relationship &amp; Consent'],
            ['icon' => 'goal.png',         'label' => 'Pregnancy &amp; Family Planning'],
            ['icon' => 'edu.png',          'label' => 'STDs &amp; Sexual Health'],
            ['icon' => 'shields.png',      'label' => 'Rape Awareness &amp; Support'],
            ['icon' => 'connectors.png',   'label' => 'Counseling'],
        ];
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-5">
            @foreach ($eduCategories as $i => $cat)
            <div class="edu-cat-card flex flex-col items-center text-center p-5 rounded-2xl bg-white border border-gray-100 shadow-sm scroll-reveal"
                 style="transition-delay: {{ $i * 0.07 }}s;">
                <div class="edu-cat-icon-wrap w-20 h-20 mb-4 flex items-center justify-center rounded-2xl">
                    <img src="{{ asset('landing/' . $cat['icon']) }}"
                         alt="{{ $cat['label'] }}"
                         class="edu-cat-icon w-12 h-12 object-contain">
                </div>
                <span class="text-sm font-semibold leading-tight" style="color: #1A1033;">
                    {!! $cat['label'] !!}
                </span>
            </div>
            @endforeach
        </div>

    </div>
</section>


{{-- ═══════════════════════════════════════════════════
     VISION & MISSION
     - Brand gradient background
     - White wave transitions top + bottom
     - White cards with SVG icons + hover lift
     - Scroll reveal
═══════════════════════════════════════════════════ --}}
<section id="vision" class="lp-vision-section lp-changing-gradient">

    {{-- Wave from white (START section) into gradient --}}
    <div class="lp-vision-wave-top" aria-hidden="true">
        <svg viewBox="0 0 1440 100" preserveAspectRatio="none" class="w-full block">
            <path fill="#ffffff" d="M0,60L80,53C160,47,320,33,480,40C640,47,800,73,960,76C1120,80,1280,60,1360,50L1440,40L1440,0L0,0Z"/>
        </svg>
    </div>

    {{-- Section content --}}
    <div class="max-w-7xl mx-auto px-6 lg:px-8 py-16 lg:py-24">

        {{-- Section header --}}
        <div class="text-center mb-14 scroll-reveal">
            <div class="lp-section-badge lp-section-badge-light inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full mb-4 text-xs font-bold tracking-widest uppercase">
                Who We Are
            </div>
            <h2 class="text-3xl lg:text-4xl font-bold text-white mb-3">
                Our Vision &amp; Mission
            </h2>
            <p class="lp-section-description-light text-base max-w-xl mx-auto leading-relaxed">
                Guided by a clear purpose — to make sexual health education accessible,
                accurate, and empowering for every Filipino.
            </p>
        </div>

        {{-- Two cards --}}
        <div class="grid md:grid-cols-2 gap-6 lg:gap-8">

            {{-- Vision --}}
            <div class="vision-card scroll-reveal" style="transition-delay: 0.15s;">
                <div class="vision-icon-wrap">
                    {{-- Eye / binoculars SVG --}}
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <h3 class="vision-card-title">Our Vision</h3>
                <p class="vision-card-body">
                    To create a sexually healthy and empowered Filipino society where every individual,
                    especially youth, has equitable access to accurate, stigma-free sexual education —
                    fostering informed choices and reducing health disparities through innovative digital solutions.
                </p>
            </div>

            {{-- Mission --}}
            <div class="vision-card scroll-reveal" style="transition-delay: 0.3s;">
                <div class="vision-icon-wrap">
                    {{-- Target / bullseye SVG --}}
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10" stroke-width="2"/>
                        <circle cx="12" cy="12" r="6" stroke-width="2"/>
                        <circle cx="12" cy="12" r="2" stroke-width="2"/>
                    </svg>
                </div>
                <h3 class="vision-card-title">Our Mission</h3>
                <p class="vision-card-body">
                    To develop and deliver a user-friendly sex education platform offering interactive,
                    culturally sensitive content on consent, STI prevention, and relationships.
                    By collaborating with educators, health workers, and communities, we promote
                    comprehensive sexual wellness and empower decision-making across the Philippines.
                </p>
            </div>

        </div>
    </div>

    {{-- Wave from gradient into #F9F7FF (Who Is This For section) --}}
    <div class="lp-vision-wave-bottom" aria-hidden="true">
        <svg viewBox="0 0 1440 100" preserveAspectRatio="none" class="w-full block">
            <path fill="#F9F7FF" d="M0,40L80,46C160,52,320,65,480,62C640,58,800,38,960,32C1120,26,1280,44,1360,53L1440,60L1440,100L0,100Z"/>
        </svg>
    </div>
</section>


{{-- ═══════════════════════════════════════════════════
     WHO IS THIS FOR — 3 age bracket cards
     Hover: lift + icon scale + arrow translate
═══════════════════════════════════════════════════ --}}
<section id="for-who" class="lp-soft-bg py-20 lg:py-28">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">

        <div class="text-center mb-14 scroll-reveal">
            <div class="lp-section-badge inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full mb-4 text-xs font-bold tracking-widest uppercase">
                Who Is This For?
            </div>
            <h2 class="lp-section-title text-3xl lg:text-4xl font-bold mb-3">
                Age-appropriate learning,<br class="hidden sm:block"> designed for everyone
            </h2>
            <p class="text-gray-500 text-base max-w-xl mx-auto leading-relaxed">
                Whether you're 10 or 40, every Filipino deserves access to accurate,
                safe, and empowering sexual health education.
            </p>
        </div>

        <div class="grid md:grid-cols-3 gap-6">
            @php
            $brackets = [
                ['icon' => 'user.png',         'age' => 'Ages 7–12',  'title' => 'Kids',   'delay' => '0.1s',
                 'bullets' => ['Body basics &amp; personal safety', 'Age-appropriate anatomy', 'Healthy habits &amp; boundaries']],
                ['icon' => 'energy.png',        'age' => 'Ages 13–17', 'title' => 'Teens',  'delay' => '0.2s',
                 'bullets' => ['Puberty &amp; body changes', 'Consent &amp; healthy relationships', 'Mental health &amp; peer pressure']],
                ['icon' => 'relationship.png',  'age' => 'Ages 18+',   'title' => 'Adults', 'delay' => '0.3s',
                 'bullets' => ['Reproductive health &amp; family planning', 'STI prevention &amp; sexual wellness', 'Mature relationships &amp; communication']],
            ];
            @endphp

            @foreach ($brackets as $card)
            <div class="age-card rounded-2xl bg-white border border-gray-100 overflow-hidden scroll-reveal"
                 style="transition-delay: {{ $card['delay'] }};">
                <div class="age-card-thumb lp-brand-gradient-bg lp-changing-gradient h-44 flex items-center justify-center relative overflow-hidden">
                    <div class="lp-soft-glow-white absolute w-44 h-44 rounded-full blur-3xl opacity-20 pointer-events-none"></div>
                    <img src="{{ asset('landing/' . $card['icon']) }}"
                         alt="{{ $card['title'] }}"
                         class="age-card-icon age-card-icon-shadow relative w-24 h-24 object-contain">
                </div>
                <div class="p-6">
                    <span class="lp-chip-badge inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold tracking-widest uppercase mb-3">
                        {{ $card['age'] }}
                    </span>
                    <h3 class="lp-title-ink text-xl font-bold mb-3">{{ $card['title'] }}</h3>
                    <ul class="space-y-2">
                        @foreach ($card['bullets'] as $bullet)
                        <li class="flex items-start gap-2 text-sm text-gray-500">
                            <svg class="lp-brand-text w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                            {!! $bullet !!}
                        </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('register') }}"
                              class="age-card-link lp-brand-text inline-flex items-center gap-1.5 mt-5 text-sm font-semibold">
                        Start exploring
                        <svg class="w-3.5 h-3.5 age-card-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════════════════
     FEATURES — 6-icon grid 2×3
     Hover: lift + icon spin + title gradient
═══════════════════════════════════════════════════ --}}
<section id="features" class="py-20 lg:py-28 bg-white">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">

        <div class="text-center mb-14 scroll-reveal">
            <div class="lp-section-badge inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full mb-4 text-xs font-bold tracking-widest uppercase">
                Platform Features
            </div>
            <h2 class="lp-section-title text-3xl lg:text-4xl font-bold mb-3">
                Everything you need to learn confidently
            </h2>
            <p class="text-gray-500 text-base max-w-xl mx-auto leading-relaxed">
                Built specifically for Filipino learners — interactive, gamified, and structured
                to make sexual health education genuinely enjoyable.
            </p>
        </div>

        @php
        $features = [
            ['module.png',  'Structured Learning Modules', 'Age-appropriate lessons on anatomy, consent, relationships, and sexual health, arranged into guided learning paths.', '0.1s'],
            ['goal.png',    'Interactive Quizzes', 'Check what you learned with built-in quizzes and get immediate feedback to strengthen understanding.', '0.2s'],
            ['edu.png',     'Verified Certificates', 'Earn shareable certificates when you complete modules and pass required assessments.', '0.3s'],
            ['shields.png', 'Progress Protection', 'Use learning shields so one failed quiz does not break your momentum while you practice.', '0.4s'],
            ['streak.png',  'Gamified Motivation', 'Stay consistent through streaks, points, and milestone rewards designed to keep learners engaged.', '0.5s'],
            ['connectors.png', 'Guardian and Educator Support', 'Enable safe guidance through guardian account tools, instructor-authored modules, and moderated communication.', '0.6s'],
        ];
        @endphp

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($features as [$icon, $title, $desc, $delay])
            <div class="feature-card rounded-2xl bg-white border border-gray-100 p-6 shadow-sm scroll-reveal"
                 style="transition-delay: {{ $delay }};">
                <div class="feature-card-icon w-14 h-14 mb-4 flex-shrink-0">
                    <img src="{{ asset('landing/' . $icon) }}" alt="{{ $title }}" class="w-full h-full object-contain">
                </div>
                <h3 class="feature-card-title lp-title-ink font-bold text-base mb-2">{{ $title }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $desc }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════════════════
     DOWNLOAD APP HERO
     - Fresh redesign
     - Conversion-focused actions
     - Phone mockup with logo overlay
═══════════════════════════════════════════════════ --}}
<section id="download-app" class="lp-mobilepromo-section py-24 lg:py-28">
    <div class="max-w-7xl mx-auto px-6 lg:px-8">
        <div class="lp-mobilepromo-layout scroll-reveal">
            <div class="lp-mobilepromo-copy">
                <span class="lp-mobilepromo-tag">Mobile Learning App</span>

                <h2 class="lp-mobilepromo-title">
                    Learn Better With
                    Conscious Connections
                </h2>

                <p class="lp-mobilepromo-description">
                    A focused mobile experience for lessons, activities, and progress tracking.
                    Built for clarity, safety, and everyday learning consistency.
                </p>

                <div class="lp-mobilepromo-qr">
                    <img
                        src="{{ route('landing.apk.qr') }}"
                        alt="QR code to download the Conscious Connections APK"
                        class="lp-mobilepromo-qr-image"
                    >
                    <div class="lp-mobilepromo-qr-copy">
                        <p class="lp-mobilepromo-qr-title">Scan QR to download APK</p>
                        <a href="{{ route('landing.apk') }}" class="lp-mobilepromo-qr-btn">Download APK</a>
                    </div>
                </div>
            </div>

            <div class="lp-mobilepromo-visual" aria-hidden="true">
                <div class="lp-mobilepromo-logo-overlay"></div>

                <div class="lp-mobilepromo-phone-wrap">
                    <div class="lp-mobilepromo-phone">
                        <div class="lp-mobilepromo-screen">
                            <img src="{{ asset('landing/sign.png') }}" alt="Conscious Connections" class="lp-mobilepromo-logo" />
                            <p class="lp-mobilepromo-brand" aria-label="Conscious Connections">
                                <span>CONSCIOUS</span>
                                <span>CONNECTIONS</span>
                            </p>
                            <div class="lp-mobilepromo-gesture-bar"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


{{-- ═══════════════════════════════════════════════════
     FOOTER — 4-column full, brand gradient
═══════════════════════════════════════════════════ --}}
<footer class="lp-brand-gradient-bg lp-changing-gradient">
    <div class="max-w-7xl mx-auto px-6 lg:px-8 py-14">
        <div class="lp-footer-divider grid sm:grid-cols-2 lg:grid-cols-4 gap-10 pb-10 border-b">

            <div class="sm:col-span-2 lg:col-span-1">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2.5 mb-4">
                    <img src="{{ asset('landing/sign.png') }}" alt="Conscious Connections" class="h-9 w-auto object-contain">
                    <div class="flex flex-col leading-none gap-0.5">
                        <span class="text-[17px] font-black tracking-wide text-white">Conscious Connections</span>
                    </div>
                </a>
                <p class="lp-footer-muted text-sm leading-relaxed max-w-[210px]">
                    Providing quality sexual health education for every Filipino, judgment-free.
                </p>
                <div class="flex gap-2.5 mt-5">
                    @foreach ([['Facebook','M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z'],['Twitter','M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z']] as [$name, $path])
                    <a href="#" aria-label="{{ $name }}"
                       class="lp-footer-social w-9 h-9 rounded-full flex items-center justify-center transition-all hover:scale-110">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="{{ $path }}"/>
                        </svg>
                    </a>
                    @endforeach
                    <a href="#" aria-label="Instagram"
                       class="lp-footer-social w-9 h-9 rounded-full flex items-center justify-center transition-all hover:scale-110">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <rect width="20" height="20" x="2" y="2" rx="5" ry="5" stroke-width="1.75"/>
                            <path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z" stroke-width="1.75"/>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </a>
                </div>
            </div>

            <div>
                <h4 class="font-bold text-xs text-white tracking-widest uppercase mb-5">Quick Links</h4>
                <ul class="space-y-3">
                    @foreach ([['#home','Home'],['#for-who',"Who It's For"],['#features','Features'],['#vision','About']] as [$href, $label])
                    <li><a href="{{ $href }}" class="lp-footer-link text-sm">{{ $label }}</a></li>
                    @endforeach
                    <li><a href="{{ route('landing.apk') }}" class="lp-footer-link text-sm">Download APK</a></li>
                </ul>
            </div>

            <div>
                <h4 class="font-bold text-xs text-white tracking-widest uppercase mb-5">Learn</h4>
                <ul class="space-y-3">
                    @foreach ([['#','Modules'],['#','Quizzes'],['#','Certificates'],['#','Premium']] as [$href, $label])
                    <li><a href="{{ $href }}" class="lp-footer-link text-sm">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h4 class="font-bold text-xs text-white tracking-widest uppercase mb-5">Contact Us</h4>
                <ul class="space-y-3.5">
                    <li class="lp-footer-muted flex items-start gap-2.5 text-sm">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>Dasmariñas, Cavite, Philippines
                    </li>
                    <li class="lp-footer-muted flex items-center gap-2.5 text-sm">
                        <svg class="w-4 h-4 flex-shrink-0 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <a href="mailto:consciousconnections@gmail.com" class="hover:text-white transition-colors">consciousconnections@gmail.com</a>
                    </li>
                    <li class="lp-footer-muted flex items-center gap-2.5 text-sm">
                        <svg class="w-4 h-4 flex-shrink-0 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>(123) 456-7890
                    </li>
                </ul>
            </div>

        </div>
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-7">
            <p class="lp-footer-faint text-xs">
                © 2026 Conscious Connections — Sex Education Platform. All rights reserved.
            </p>
            <div class="lp-footer-faint flex items-center gap-4 text-xs">
                <a href="{{ route('privacy') }}" class="hover:text-white transition-colors">Privacy Policy</a>
                <span class="w-px h-3 bg-white/20"></span>
                <a href="{{ route('terms') }}" class="hover:text-white transition-colors">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>

{{-- ═══════════════════════════════════════════════════
     BACK TO TOP BUTTON — fixed bottom-right
     Appears after 400px scroll, smooth scroll on click
═══════════════════════════════════════════════════ --}}
<button id="lp-back-to-top" class="lp-changing-gradient" aria-label="Back to top">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
    </svg>
</button>

@endsection


@push('head')
<style>

:root {
    --lp-purple-1: #A30EB2;
    --lp-purple-2: #730DB1;
    --lp-purple-3: #3B0CB1;
    --lp-title-ink: #1A1033;
    --lp-badge-bg: rgba(163,14,178,0.07);
    --lp-badge-border: rgba(163,14,178,0.14);
    --lp-footer-muted: rgba(255,255,255,0.6);
    --lp-footer-faint: rgba(255,255,255,0.45);
    --lp-gradient-main: linear-gradient(135deg, var(--lp-purple-1), var(--lp-purple-3));
    --lp-gradient-brand: linear-gradient(135deg, var(--lp-purple-1), var(--lp-purple-2), var(--lp-purple-3));
    --lp-gradient-shift-size: 220% 220%;
}

.lp-btn-gradient {
    background: var(--lp-gradient-main);
    box-shadow: 0 4px 14px rgba(115,13,177,0.4);
}

.lp-btn-gradient-strong {
    background: var(--lp-gradient-brand);
    box-shadow: 0 8px 28px rgba(115,13,177,0.38);
}

.lp-brand-gradient-bg {
    background: var(--lp-gradient-brand);
}

.lp-changing-gradient {
    background-image: var(--lp-gradient-brand);
    background-size: var(--lp-gradient-shift-size);
    animation: lpGradientMove 8s ease infinite;
}

.lp-brand-text {
    color: var(--lp-purple-2);
}

.lp-title-ink {
    color: var(--lp-title-ink);
}

.lp-soft-bg {
    background: #F9F7FF;
}

.lp-soft-glow-white {
    background: rgba(255,255,255,0.6);
}

.lp-section-badge {
    background: var(--lp-badge-bg);
    color: var(--lp-purple-2);
    border: 1px solid var(--lp-badge-border);
}

.lp-section-badge-light {
    background: rgba(255,255,255,0.15);
    color: white;
    border-color: rgba(255,255,255,0.25);
}

.lp-section-title {
    color: var(--lp-title-ink);
}

.lp-section-description-light {
    color: rgba(255,255,255,0.72);
}

.lp-chip-badge {
    background: var(--lp-badge-bg);
    color: var(--lp-purple-2);
}

.age-card-icon-shadow {
    filter: drop-shadow(0 8px 20px rgba(0,0,0,0.3));
}

.lp-footer-divider {
    border-color: rgba(255,255,255,0.12);
}

.lp-footer-muted {
    color: var(--lp-footer-muted);
}

.lp-footer-faint {
    color: var(--lp-footer-faint);
}

.lp-footer-link {
    color: var(--lp-footer-muted);
    transition: color 0.2s ease;
}

.lp-footer-link:hover {
    color: white;
}

.lp-footer-social {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.18);
}

/* ══════════════════════════════════════════════════
   NAVBAR
══════════════════════════════════════════════════ */

/* Brand name: gradient text when scrolled */
.nav-brand-scrolled .nav-brand-label,
.nav-brand-scrolled .nav-brand-name {
    background: var(--lp-gradient-main);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Nav link underline sweep */
.nav-link {
    position: relative;
    padding-bottom: 2px;
}
.nav-link::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 100%;
    height: 2px;
    border-radius: 2px;
    background: var(--lp-gradient-main);
    transform: scaleX(0);
    transform-origin: center;
    transition: transform 0.25s ease;
}
.nav-link:hover::after {
    transform: scaleX(1);
}

.nav-link[data-active='true']::after {
    transform: scaleX(1);
}

/* CTA shimmer sweep on hover */
.nav-cta-btn::after {
    content: '';
    position: absolute;
    top: 0;
    left: -120%;
    width: 60%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.28), transparent);
    transform: skewX(-20deg);
    transition: none;
}
.nav-cta-btn:hover {
    opacity: 0.92;
    transform: scale(1.02);
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.nav-cta-btn:hover::after {
    left: 160%;
    transition: left 0.55s ease;
}

/* Login button — gradient fill on hover */
.nav-login-btn {
    transition: all 0.25s ease;
}
.nav-login-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    background: var(--lp-gradient-main);
    opacity: 0;
    transition: opacity 0.25s ease;
    z-index: 0;
}
.nav-login-btn:hover::before {
    opacity: 1;
}
.nav-login-btn:hover {
    color: white !important;
    border-color: transparent !important;
}
/* Scrolled state login hover */
.nav-scrolled .nav-login-btn:hover {
    color: white !important;
}


/* ══════════════════════════════════════════════════
   HERO HEADER
══════════════════════════════════════════════════ */
.lp-header {
    position: relative;
    padding: 180px 40px 200px;
    text-align: center;
    overflow: hidden;
    color: #fff;
}
@keyframes lpGradientMove {
    0%   { background-position: 0% 50%;   }
    50%  { background-position: 100% 50%; }
    100% { background-position: 0% 50%;   }
}

/* 7 Orbs */
.lp-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(2px);
    opacity: 0.6;
    pointer-events: none;
    z-index: 1;
    animation: lpOrbFloat 12s ease-in-out infinite;
}
.lp-orb-1 { width: 140px; height: 140px; background: radial-gradient(circle, #FAE31A, transparent 40%); top: 20%; left: 10%; }
.lp-orb-2 { width: 200px; height: 200px; background: radial-gradient(circle, #E41C93, transparent 60%); bottom: 18%; right: 12%; animation-delay: 4s; }
.lp-orb-3 { width: 120px; height: 120px; background: radial-gradient(circle, #37088A, transparent 60%); top: 10%; right: 30%; animation-delay: 7s; }
.lp-orb-4 { width: 75px;  height: 75px;  background: radial-gradient(circle, rgba(255,255,255,0.9), transparent 50%); top: 58%; left: 28%; animation-delay: 2s; opacity: 0.35; }
.lp-orb-5 { width: 155px; height: 155px; background: radial-gradient(circle, #C913C9, transparent 60%); top: 5%; left: 50%; animation-delay: 5s; opacity: 0.45; }
.lp-orb-6 { width: 95px;  height: 95px;  background: radial-gradient(circle, #FF8C00, transparent 60%); bottom: 32%; left: 6%; animation-delay: 9s; opacity: 0.4; }
.lp-orb-7 { width: 55px;  height: 55px;  background: radial-gradient(circle, #00D4FF, transparent 60%); top: 42%; right: 6%; animation-delay: 3s; opacity: 0.35; }

@keyframes lpOrbFloat {
    0%,  100% { transform: translateY(0); }
    50%        { transform: translateY(-30px); }
}

/* Heading block */
.lp-head {
    position: relative;
    z-index: 2;
}
.lp-head-glow {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 560px;
    max-width: 90vw;
    height: 180px;
    background: radial-gradient(ellipse at center, rgba(255,255,255,0.14), transparent 72%);
    filter: blur(28px);
    pointer-events: none;
    z-index: 0;
}

/* Mixed solid/outline heading */
.lp-hero-title {
    position: relative;
    z-index: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    margin: 0;
    line-height: 1.05;
}
.lp-title-line1 {
    display: block;
    font-size: clamp(38px, 7vw, 66px);
    font-weight: 900;
    color: white;
    text-shadow: 0 0 50px rgba(255,255,255,0.25);
    opacity: 0;
    animation: lpSlideUpFade 0.9s ease 0.1s forwards, lpLetterExpand 1.6s ease 0.2s forwards;
}
.lp-title-line2 {
    display: block;
    font-size: clamp(38px, 7vw, 66px);
    font-weight: 900;
    color: transparent;
    -webkit-text-stroke: 2.5px rgba(255,255,255,0.8);
    opacity: 0;
    animation: lpSlideUpFade 0.9s ease 0.25s forwards, lpLetterExpand 1.6s ease 0.35s forwards;
}
@keyframes lpSlideUpFade {
    from { opacity: 0; transform: translateY(28px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes lpLetterExpand {
    from { letter-spacing: -3px; }
    to   { letter-spacing: 6px;  }
}

/* Gradient rule between title and subtitle */
.lp-title-rule {
    width: 0;
    height: 1.5px;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.6), transparent);
    margin: 18px auto 20px;
    position: relative;
    z-index: 1;
    animation: lpRuleExpand 1.4s cubic-bezier(0.22,1,0.36,1) 0.9s forwards;
}
@keyframes lpRuleExpand {
    from { width: 0; }
    to   { width: min(380px, 55%); }
}

.lp-hero-sub {
    font-size: 18px;
    position: relative;
    z-index: 1;
    opacity: 0;
    animation: lpSlideUpFade 1s ease 0.55s forwards;
}

/* Wave */
.lp-wave {
    position: absolute;
    bottom: -1px;
    left: 0;
    width: 100%;
    height: 120px;
    display: block;
}

/* Corner glow orbs (ported from groupmate's hero ::before / ::after) */
.lp-header::before,
.lp-header::after {
    content: "";
    position: absolute;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle at center, rgba(255,255,255,0.22), transparent 65%);
    border-radius: 50%;
    pointer-events: none;
    z-index: 1;
    animation: lpOrbFloat 10s ease-in-out infinite;
}
.lp-header::before {
    top: -80px;
    left: -80px;
}
.lp-header::after {
    bottom: -80px;
    right: -80px;
    animation-delay: 3s;
}


/* ══════════════════════════════════════════════════
   EDU CATEGORY SECTION
══════════════════════════════════════════════════ */
.edu-cat-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}
.edu-cat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 36px rgba(115,13,177,0.12);
    border-color: rgba(163,14,178,0.2) !important;
}
.edu-cat-icon {
    transition: transform 0.35s ease;
}
.edu-cat-card:hover .edu-cat-icon {
    transform: scale(1.15);
}

/* ══════════════════════════════════════════════════
   START LEARNING CTA BUTTON
══════════════════════════════════════════════════ */
@keyframes ctaBtnGlow {
    0%, 100% { box-shadow: 0 8px 28px rgba(115,13,177,0.38); }
    50%       { box-shadow: 0 8px 48px rgba(163,14,178,0.65); }
}
.lp-start-cta-btn {
    /* Entrance: slides up from below after LEARNING text (delay 0.65s) */
    animation:
        lpSlideUpFade 0.9s cubic-bezier(0.22,1,0.36,1) 0.65s both,
        ctaBtnGlow    2.8s ease-in-out 1.8s infinite;
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.lp-start-cta-btn:hover {
    opacity: 0.92;
    transform: scale(1.04) translateY(-2px);
    animation: none; /* pause glow on hover, CSS transition takes over */
    box-shadow: 0 12px 36px rgba(163,14,178,0.55);
}
.lp-start-cta-btn:active {
    transform: scale(0.97);
}
/* Shimmer sweep on hover — same pattern as nav-cta-btn */
.lp-start-cta-btn::after {
    content: '';
    position: absolute;
    top: 0;
    left: -120%;
    width: 60%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.28), transparent);
    transform: skewX(-20deg);
}
.lp-start-cta-btn:hover::after {
    left: 160%;
    transition: left 0.55s ease;
}

/* ══════════════════════════════════════════════════
   START LEARNING SECTION
══════════════════════════════════════════════════ */
.lp-start-section {
    position: relative;
    background: #fff;
    padding: 0 10% 100px;
    overflow: hidden;
}
.lp-sign {
    position: relative;
    min-height: 360px;
    padding-top: 40px;
    line-height: 1;
}

/* Heading text */
.lp-start {
    color: #6D0EB2;
    font-size: clamp(56px, 10vw, 120px);
    font-weight: 700;
    opacity: 0;
    transform: translateX(120px);
    animation: lpPanFadeLeft 2s cubic-bezier(0.22,1,0.36,1) 0.2s forwards;
    will-change: transform, opacity;
    margin: 0;
    line-height: 1;
    position: relative;
    z-index: 2;
}
.lp-learning {
    color: transparent;
    font-size: clamp(56px, 10vw, 120px);
    font-weight: 700;
    -webkit-text-stroke: 2px #6D0EB2;
    opacity: 0;
    transform: translateX(120px);
    animation: lpPanFadeLeft 2s cubic-bezier(0.22,1,0.36,1) 0.4s forwards;
    will-change: transform, opacity;
    margin: 0;
    line-height: 1;
    position: relative;
    z-index: 2;
}
@keyframes lpPanFadeLeft {
    0%   { opacity: 0; transform: translateX(120px); }
    100% { opacity: 1; transform: translateX(0); }
}

/* sign.png — grounded right */
.lp-sign-wrap {
    position: absolute;
    bottom: -20px;
    right: -40px;
    z-index: 10;
    pointer-events: none;
}
.lp-sign-platform {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 130%;
    height: 130%;
    border-radius: 50%;
    pointer-events: none;
    z-index: 0;
}
.lp-hero-image {
    position: relative;
    z-index: 1;
    width: clamp(180px, 34vw, 460px);
    max-width: 100%;
    opacity: 0;
    transform: rotate(-50deg);
    animation: lpSignFadeIn 1.8s cubic-bezier(0.22,1,0.36,1) 0.5s forwards;
    filter: drop-shadow(0 24px 40px rgba(115,13,177,0.3));
}
@keyframes lpSignFadeIn {
    from { opacity: 0; transform: rotate(-50deg) scale(0.9); }
    to   { opacity: 1; transform: rotate(-8deg) scale(1); }
}

@media (max-width: 768px) {
    .lp-books-wrap { display: none; }
    .lp-sign-wrap  { position: static; text-align: center; margin-top: 20px; }
    .lp-hero-image { width: 180px; transform: rotate(-50deg); }
    .lp-sign       { min-height: auto; padding-bottom: 40px; }
}


/* ══════════════════════════════════════════════════
   VISION & MISSION SECTION
══════════════════════════════════════════════════ */
.lp-vision-section {
    position: relative;
    overflow: hidden;
}
.lp-vision-wave-top,
.lp-vision-wave-bottom {
    line-height: 0;
}

.vision-card {
    background: white;
    border-radius: 20px;
    padding: 36px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    border: 1.5px solid rgba(255,255,255,0.6);
    position: relative;
    overflow: hidden;
    transition: transform 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
}
.vision-card::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(163,14,178,0.05) 0%, rgba(59,12,177,0.05) 100%);
    opacity: 0;
    transition: opacity 0.35s ease;
    pointer-events: none;
}
.vision-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 28px 64px rgba(0,0,0,0.22), 0 0 0 1.5px rgba(163,14,178,0.3);
    border-color: rgba(163,14,178,0.25);
}
.vision-card:hover::before {
    opacity: 1;
}
.vision-icon-wrap {
    width: 52px;
    height: 52px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    background: var(--lp-gradient-main);
    flex-shrink: 0;
    transition: transform 0.35s ease, box-shadow 0.35s ease;
}
.vision-card:hover .vision-icon-wrap {
    transform: scale(1.12) rotate(-6deg);
    box-shadow: 0 8px 24px rgba(163,14,178,0.45);
}
.vision-card-title {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 12px;
    background: var(--lp-gradient-main);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.vision-card-body {
    font-size: 14px;
    line-height: 1.75;
    color: #6B7280;
}


/* ══════════════════════════════════════════════════
   WHO IS THIS FOR — card hovers
══════════════════════════════════════════════════ */
.age-card {
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}
.age-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 20px 44px rgba(115,13,177,0.14);
    border-color: rgba(163,14,178,0.25) !important;
}
.age-card-icon {
    transition: transform 0.4s ease;
}
.age-card:hover .age-card-icon {
    transform: scale(1.12);
}
.age-card-arrow {
    transition: transform 0.25s ease;
}
.age-card:hover .age-card-arrow {
    transform: translateX(4px);
}


/* ══════════════════════════════════════════════════
   FEATURES — card hovers
══════════════════════════════════════════════════ */
.feature-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease, border-color 0.3s ease;
}
.feature-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(163,14,178,0.13);
    border-color: rgba(163,14,178,0.2) !important;
}
.feature-card-icon {
    transition: transform 0.35s ease;
}
.feature-card:hover .feature-card-icon {
    transform: scale(1.1) rotate(-4deg);
}
.feature-card-title {
    transition: all 0.2s ease;
}
.feature-card:hover .feature-card-title {
    background: var(--lp-gradient-main);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* ══════════════════════════════════════════════════
   DOWNLOAD APP HERO
══════════════════════════════════════════════════ */
.lp-mobilepromo-section {
    position: relative;
    overflow: hidden;
    background: #F9F7FF;
}

.lp-mobilepromo-section::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
        radial-gradient(circle at 14% 22%, rgba(163,14,178,0.1), rgba(163,14,178,0) 42%),
        radial-gradient(circle at 88% 70%, rgba(59,12,177,0.08), rgba(59,12,177,0) 46%);
}

.lp-mobilepromo-layout {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 1.08fr 0.92fr;
    align-items: center;
    gap: clamp(1.6rem, 3vw, 3.1rem);
}

.lp-mobilepromo-tag {
    display: inline-flex;
    border-radius: 9999px;
    border: 1px solid var(--lp-badge-border);
    background: var(--lp-badge-bg);
    color: var(--lp-purple-2);
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    padding: 0.36rem 0.84rem;
}

.lp-mobilepromo-title {
    margin-top: 0.95rem;
    max-width: 12.2ch;
    font-size: clamp(2.05rem, 5.4vw, 3.8rem);
    line-height: 0.95;
    letter-spacing: -0.03em;
    font-weight: 900;
    color: var(--lp-title-ink);
}

.lp-mobilepromo-description {
    margin-top: 1.08rem;
    max-width: 34rem;
    color: #6B7280;
    font-size: 1.02rem;
    line-height: 1.65;
}

.lp-mobilepromo-qr {
    margin-top: 1.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.92rem;
    padding: 0.75rem 0.85rem;
    border-radius: 16px;
    border: 1px solid rgba(163,14,178,0.15);
    background: rgba(255,255,255,0.92);
    box-shadow: 0 10px 28px rgba(115,13,177,0.08);
}

.lp-mobilepromo-qr-image {
    width: 82px;
    height: 82px;
    border-radius: 10px;
    background: #fff;
    padding: 4px;
    border: 1px solid rgba(17, 24, 39, 0.08);
    object-fit: cover;
}

.lp-mobilepromo-qr-copy {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.lp-mobilepromo-qr-title {
    margin: 0;
    color: #0f172a;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.2;
}

.lp-mobilepromo-qr-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 0.1rem;
    border-radius: 9999px;
    min-height: 2.45rem;
    padding: 0.58rem 1.35rem;
    background: var(--lp-gradient-main);
    color: #ffffff;
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1;
    text-decoration: none;
    box-shadow: 0 8px 18px rgba(115, 13, 177, 0.28);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.lp-mobilepromo-qr-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 18px rgba(115, 13, 177, 0.34);
}

.lp-mobilepromo-watch {
    display: inline-flex;
    align-items: center;
    gap: 0.72rem;
    color: #222b55;
    font-size: 0.97rem;
    font-weight: 700;
}

.lp-mobilepromo-watch-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.55rem;
    height: 2.55rem;
    border-radius: 9999px;
    background: var(--lp-gradient-brand);
    color: #fff;
    box-shadow: 0 7px 16px rgba(115,13,177,0.2);
}

.lp-mobilepromo-visual {
    position: relative;
    min-height: 470px;
}

.lp-mobilepromo-logo-overlay {
    position: absolute;
    top: 50%;
    right: -2%;
    width: 510px;
    height: 510px;
    transform: translateY(-50%);
    background: linear-gradient(135deg, rgba(125, 181, 255, 0.95), rgba(29, 78, 216, 0.95));
    -webkit-mask: url('{{ asset('landing/sign.png') }}') center center / contain no-repeat;
    mask: url('{{ asset('landing/sign.png') }}') center center / contain no-repeat;
    opacity: 0.16;
    z-index: 1;
    pointer-events: none;
    filter: drop-shadow(0 14px 24px rgba(37, 29, 79, 0.2));
}

.lp-mobilepromo-phone-wrap {
    position: absolute;
    right: 48px;
    top: 16px;
    z-index: 3;
    transform: perspective(1000px) rotateY(-10deg) rotateX(2deg);
    animation: lpMobilePromoFloat 6.5s ease-in-out infinite;
}

@keyframes lpMobilePromoFloat {
    0%, 100% { transform: perspective(1000px) rotateY(-10deg) rotateX(2deg) translateY(0); }
    50%      { transform: perspective(1000px) rotateY(-9deg) rotateX(2deg) translateY(-9px); }
}

.lp-mobilepromo-phone {
    width: 224px;
    height: 430px;
    position: relative;
    border: 2px solid #2563eb;
    border-radius: 38px;
    background: linear-gradient(180deg, #173988 0%, #132f76 100%);
    box-shadow: 0 30px 52px rgba(16, 41, 108, 0.34), 0 0 0 1px rgba(125, 181, 255, 0.26), inset 0 0 0 1px rgba(255,255,255,0.08);
}

.lp-mobilepromo-phone::before {
    content: '';
    position: absolute;
    top: 9px;
    left: 50%;
    transform: translateX(-50%);
    width: 62px;
    height: 5px;
    border-radius: 9999px;
    background: #5a74b3;
}

.lp-mobilepromo-phone::after {
    content: '';
    position: absolute;
    top: 8px;
    right: 72px;
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    background: radial-gradient(circle at 35% 35%, #a2ddff 0%, #5f9be8 44%, #132f76 100%);
    box-shadow: 0 0 0 2px #20459c;
}

.lp-mobilepromo-screen {
    position: relative;
    margin: 1.2rem 0.45rem 0.55rem;
    height: calc(100% - 1.75rem);
    border-radius: 31px;
    border: none;
    background: linear-gradient(165deg, #f6f8ff 0%, #edf1ff 60%, #e3ebff 100%);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 0.58rem;
    align-items: center;
    justify-content: center;
}

.lp-mobilepromo-screen::before {
    content: '';
    position: absolute;
    inset: 0;
    background:
        radial-gradient(circle at 22% 18%, rgba(255,255,255,0.7), rgba(255,255,255,0) 46%),
        radial-gradient(circle at 78% 78%, rgba(114,132,214,0.22), rgba(114,132,214,0) 42%);
}

.lp-mobilepromo-logo {
    position: relative;
    z-index: 2;
    width: 124px;
    height: auto;
    filter: drop-shadow(0 14px 24px rgba(20, 51, 123, 0.28));
}

.lp-mobilepromo-brand {
    position: relative;
    z-index: 2;
    margin: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.1rem;
    font-size: 0.68rem;
    line-height: 1;
    font-weight: 900;
    letter-spacing: 0.11em;
    text-transform: uppercase;
    color: var(--lp-purple-2);
    text-align: center;
}

.lp-mobilepromo-brand span {
    display: block;
}

.lp-mobilepromo-gesture-bar {
    position: absolute;
    z-index: 2;
    bottom: 9px;
    left: 50%;
    transform: translateX(-50%);
    width: 68px;
    height: 5px;
    border-radius: 9999px;
    background: rgba(33, 48, 90, 0.36);
}

@media (max-width: 1024px) {
    .lp-mobilepromo-layout {
        grid-template-columns: 1fr;
        gap: 2.2rem;
    }

    .lp-mobilepromo-title {
        max-width: 16ch;
    }

    .lp-mobilepromo-visual {
        min-height: 420px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .lp-mobilepromo-logo-overlay {
        left: 50%;
        right: auto;
        width: 440px;
        height: 440px;
        opacity: 0.18;
        transform: translate(-50%, -50%);
    }

    .lp-mobilepromo-phone-wrap {
        position: relative;
        right: auto;
        top: auto;
    }
}

@media (max-width: 640px) {
    .lp-mobilepromo-title {
        font-size: clamp(1.9rem, 10vw, 2.7rem);
        max-width: none;
    }

    .lp-mobilepromo-description {
        font-size: 0.98rem;
    }

    .lp-mobilepromo-qr {
        width: 100%;
        justify-content: flex-start;
        padding: 0.72rem;
    }

    .lp-mobilepromo-qr-image {
        width: 92px;
        height: 92px;
    }

    .lp-mobilepromo-watch {
        width: 100%;
        justify-content: center;
    }

    .lp-mobilepromo-visual {
        min-height: 356px;
    }

    .lp-mobilepromo-logo-overlay {
        width: 320px;
        height: 320px;
        opacity: 0.15;
    }

    .lp-mobilepromo-phone-wrap {
        animation: none;
        margin: 0 auto;
        transform: perspective(900px) rotateY(-2deg) rotateX(1deg);
    }

    .lp-mobilepromo-phone {
        width: 198px;
        height: 390px;
    }

    .lp-mobilepromo-logo {
        width: 100px;
    }

    .lp-mobilepromo-brand {
        font-size: 0.6rem;
        letter-spacing: 0.09em;
    }
}


/* ══════════════════════════════════════════════════
   SCROLL REVEAL
══════════════════════════════════════════════════ */
.scroll-reveal {
    opacity: 0;
    transform: translateY(28px);
    transition: opacity 0.65s ease, transform 0.65s ease;
}
.scroll-reveal.revealed {
    opacity: 1;
    transform: translateY(0);
}
/* ══════════════════════════════════════════════════
   BACK TO TOP BUTTON
══════════════════════════════════════════════════ */
#lp-back-to-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    color: white;
    border: none;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 18px rgba(115,13,177,0.45);
    z-index: 1000;
    transition: opacity 0.3s ease, visibility 0.3s ease, transform 0.25s ease, box-shadow 0.25s ease;
    transform: translateY(10px);
}
#lp-back-to-top.btt-visible {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
#lp-back-to-top:hover {
    transform: translateY(-3px) scale(1.08);
    box-shadow: 0 8px 28px rgba(115,13,177,0.55);
}
#lp-back-to-top:active {
    transform: translateY(0) scale(0.96);
}

@media (prefers-reduced-motion: reduce) {
    .lp-changing-gradient {
        animation: none;
        background-position: 50% 50%;
    }
}

</style>
@endpush


@push('scripts')
<script>
    // Scroll reveal
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.scroll-reveal').forEach(el => revealObserver.observe(el));

    // Back to top
    const bttBtn = document.getElementById('lp-back-to-top');
    window.addEventListener('scroll', () => {
        bttBtn.classList.toggle('btt-visible', window.scrollY > 400);
    });
    bttBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });

</script>
@endpush
