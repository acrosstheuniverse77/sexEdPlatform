{{--
| Legal + Help Modals
| Included once in auth-split-layout so every auth page gets these modals.
| Trigger by dispatching window events:
|   $dispatch('open-terms')    → Terms of Service
|   $dispatch('open-privacy')  → Privacy Policy
|   $dispatch('open-help')     → Help / FAQ
--}}

{{-- ============================================================ --}}
{{-- TERMS OF SERVICE MODAL                                       --}}
{{-- ============================================================ --}}
<div x-data="{ open: false }"
     @open-terms.window="open = true"
     x-show="open"
     x-cloak
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-[99999] flex items-center justify-center p-4 sm:p-6"
     style="display:none;">

    {{-- Backdrop --}}
    <div @click="open = false"
         class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    {{-- Panel --}}
    <div @click.stop
         class="relative w-full max-w-3xl max-h-[90vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 shrink-0"
             style="background: linear-gradient(135deg, #A30EB2 0%, #730DB1 50%, #3B0CB1 100%);">
            <div class="flex items-center gap-3">
                <img src="{{ asset('/media/Logo.png') }}" alt="Logo" class="h-8 w-auto drop-shadow">
                <div>
                    <h2 class="text-base font-bold text-white leading-tight">Terms of Service</h2>
                    <p class="text-white/70 text-xs">Last updated {{ now()->format('F d, Y') }}</p>
                </div>
            </div>
            <button @click="open = false"
                    class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Scrollable Content --}}
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-5 text-sm text-gray-700 leading-relaxed">

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">1. Introduction</h3>
                <p>Welcome to <strong>Conscious Connections</strong>. By accessing or using our platform, you agree to be bound by these Terms of Service. This platform is designed to provide age-appropriate sexual education and health information to learners of all ages in the Philippines.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">2. Age Requirements & Guardian Consent</h3>
                <div class="space-y-2">
                    <p><strong>Under 13:</strong> Children cannot create accounts without guardian consent. A guardian or legal guardian must register, verify their email, and create a child account on the child's behalf.</p>
                    <p><strong>Ages 13–17:</strong> May create their own accounts but must complete email verification before accessing the platform.</p>
                    <p><strong>Adults (18+):</strong> May create accounts, enroll in modules, and optionally manage accounts for their children.</p>
                </div>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">3. User Accounts</h3>
                <ul class="list-disc list-inside pl-2 space-y-1">
                    <li>You must provide accurate, complete information during registration.</li>
                    <li>Only Gmail accounts (@gmail.com) are currently accepted for email verification.</li>
                    <li>You are responsible for maintaining the confidentiality of your credentials.</li>
                    <li>Never share your password with anyone.</li>
                    <li>Guardians are responsible for securing their child accounts.</li>
                </ul>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">4. Acceptable Use</h3>
                <p class="mb-2">You agree to use the platform only for lawful educational purposes. You may <strong>not</strong>:</p>
                <ul class="list-disc list-inside pl-2 space-y-1">
                    <li>Share inappropriate content or engage in harassment.</li>
                    <li>Attempt to access other users' accounts or data.</li>
                    <li>Misrepresent your age or identity.</li>
                    <li>Use the platform for commercial purposes without authorization.</li>
                </ul>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">5. Guardian Rights & Responsibilities</h3>
                <p class="mb-2">Guardians who create accounts for children under 13 have the right to view their child's progress, review quiz attempts, and request deletion of the child's account.</p>
                <p>Guardians are responsible for monitoring their child's platform usage and ensuring their child understands safe online behavior.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">6. Content & Intellectual Property</h3>
                <p>All educational content, modules, quizzes, and materials are the intellectual property of Conscious Connections and its content creators. You may not reproduce, distribute, or sell platform content without permission.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">7. Educational Disclaimer</h3>
                <p>Information on this platform is for educational purposes only and does not replace professional medical advice. Always consult a qualified healthcare provider for medical concerns.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">8. Limitation of Liability</h3>
                <p>Conscious Connections is provided "as is" for educational purposes. We are not liable for any damages arising from your use of the platform, content inaccuracies, or service interruptions.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">9. Termination</h3>
                <p>We reserve the right to suspend or terminate accounts that violate these Terms. You may request account deletion at any time. Guardians may request immediate deletion of child accounts and we will comply within 30 days.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">10. Payments and Refund Policy</h3>
                <p>All subscription and payment transactions are final. We do not accept refunds, and no refund processing is offered after payment is completed.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">11. Changes to Terms</h3>
                <p>We may update these Terms from time to time. Significant changes will be communicated via email to registered users. Continued use after changes constitutes acceptance of the new terms.</p>
            </section>

        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 shrink-0 flex items-center justify-between">
            <p class="text-xs text-gray-500">By using the platform, you accept these terms.</p>
            <button @click="open = false"
                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                    class="inline-flex items-center justify-center px-6 py-2.5 text-sm font-semibold text-white rounded-xl shadow-md hover:opacity-90 transition-all duration-200">
                Close
            </button>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- PRIVACY POLICY MODAL                                         --}}
{{-- ============================================================ --}}
<div x-data="{ open: false }"
     @open-privacy.window="open = true"
     x-show="open"
     x-cloak
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-[99999] flex items-center justify-center p-4 sm:p-6"
     style="display:none;">

    {{-- Backdrop --}}
    <div @click="open = false"
         class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    {{-- Panel --}}
    <div @click.stop
         class="relative w-full max-w-3xl max-h-[90vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 shrink-0"
             style="background: linear-gradient(135deg, #A30EB2 0%, #730DB1 50%, #3B0CB1 100%);">
            <div class="flex items-center gap-3">
                <img src="{{ asset('/media/Logo.png') }}" alt="Logo" class="h-8 w-auto drop-shadow">
                <div>
                    <h2 class="text-base font-bold text-white leading-tight">Privacy Policy</h2>
                    <p class="text-white/70 text-xs">Last updated {{ now()->format('F d, Y') }}</p>
                </div>
            </div>
            <button @click="open = false"
                    class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Scrollable Content --}}
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-5 text-sm text-gray-700 leading-relaxed">

            <div class="bg-purple-50 border border-purple-200 rounded-xl p-4">
                <p class="text-sm text-purple-900 font-medium">COPPA Compliance Statement</p>
                <p class="text-xs text-purple-700 mt-1">We comply with the Children's Online Privacy Protection Act (COPPA). We do not knowingly collect personal information from children under 13 without verifiable guardian consent. Guardians have full control over their child's account and can review, modify, or delete their child's information at any time.</p>
            </div>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">1. Information We Collect</h3>
                <p class="mb-2"><strong>For all users:</strong></p>
                <ul class="list-disc list-inside pl-2 space-y-1 mb-3">
                    <li>Full name, birthdate, email address, password (encrypted)</li>
                    <li>Location: region, province, city, barangay (Philippines)</li>
                    <li>Username and optional profile bio</li>
                </ul>
                <p class="mb-2"><strong>For children under 13 (collected via guardian):</strong></p>
                <ul class="list-disc list-inside pl-2 space-y-1">
                    <li>Guardian's name, email, and verified relationship</li>
                    <li>Timestamp of when guardian created the child account</li>
                </ul>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">2. How We Use Your Information</h3>
                <ul class="list-disc list-inside pl-2 space-y-1">
                    <li>To provide age-appropriate educational content</li>
                    <li>To verify your identity and email address</li>
                    <li>To track learning progress and award gamification points</li>
                    <li>To communicate important platform updates</li>
                    <li>To maintain the security of your account</li>
                </ul>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">3. Data Protection for Children</h3>
                <p class="mb-2">Children's accounts are created and managed by guardians. We:</p>
                <ul class="list-disc list-inside pl-2 space-y-1">
                    <li>Do not display children's personal information publicly</li>
                    <li>Do not allow children to share personal information in public forums</li>
                    <li>Allow guardians to review and delete their child's data at any time</li>
                    <li>Do not sell or share children's data with third parties</li>
                </ul>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">4. Data Security</h3>
                <p>We implement industry-standard security measures including password hashing (bcrypt), HTTPS encryption, and session management. However, no system is 100% secure — please keep your credentials confidential.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">5. Your Rights</h3>
                <ul class="list-disc list-inside pl-2 space-y-1">
                    <li><strong>Access:</strong> Request a copy of your personal data</li>
                    <li><strong>Correction:</strong> Update inaccurate information in your profile</li>
                    <li><strong>Deletion:</strong> Request permanent deletion of your account and data</li>
                    <li><strong>Portability:</strong> Request an export of your data</li>
                </ul>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">6. Cookies & Session Data</h3>
                <p>We use session cookies for authentication and to maintain your login state. No third-party tracking cookies are used on this platform.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">7. Changes to This Policy</h3>
                <p>We may update this Privacy Policy as needed. We will notify registered users via email of significant changes. Continued use of the platform constitutes acceptance of the updated policy.</p>
            </section>

            <section>
                <h3 class="text-base font-semibold text-purple-900 mb-2">8. Contact</h3>
                <p>For privacy concerns, data requests, or questions, contact us at <strong>privacy@consciousconnections.ph</strong>.</p>
            </section>

        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 shrink-0 flex items-center justify-between">
            <p class="text-xs text-gray-500">Your privacy is important to us.</p>
            <button @click="open = false"
                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                    class="inline-flex items-center justify-center px-6 py-2.5 text-sm font-semibold text-white rounded-xl shadow-md hover:opacity-90 transition-all duration-200">
                Close
            </button>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- HELP MODAL                                                    --}}
{{-- ============================================================ --}}
<div x-data="{ open: false, activeQ: null }"
     @open-help.window="open = true"
     x-show="open"
     x-cloak
     @keydown.escape.window="open = false"
     class="fixed inset-0 z-[99999] flex items-center justify-center p-4 sm:p-6"
     style="display:none;">

    {{-- Backdrop --}}
    <div @click="open = false"
         class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
    </div>

    {{-- Panel --}}
    <div @click.stop
         class="relative w-full max-w-2xl max-h-[90vh] bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 shrink-0"
             style="background: linear-gradient(135deg, #A30EB2 0%, #730DB1 50%, #3B0CB1 100%);">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-white/20 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-white leading-tight">Help Center</h2>
                    <p class="text-white/70 text-xs">Frequently asked questions</p>
                </div>
            </div>
            <button @click="open = false"
                    class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 text-white hover:bg-white/20 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Scrollable Content --}}
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-3">

            {{-- Quick contact banner --}}
            <div class="flex items-center gap-3 bg-purple-50 border border-purple-200 rounded-xl px-4 py-3 mb-4">
                <svg class="w-5 h-5 text-purple-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <div>
                    <p class="text-xs font-semibold text-purple-900">Still need help?</p>
                    <p class="text-xs text-purple-700">Email us at <strong>support@consciousconnections.ph</strong></p>
                </div>
            </div>

            {{-- FAQ Accordion Items --}}
            @php
                $faqs = [
                    ['q' => 'What email format is accepted?',
                     'a' => 'Currently, only Gmail accounts (@gmail.com) are accepted for registration. This helps us ensure reliable email verification. Support for other email providers is planned for a future update.'],
                    ['q' => 'How do I verify my email?',
                     'a' => 'After creating your account, we send a verification link to your Gmail address. Open the email and click the link. If you don\'t see it, check your Spam or Promotions folder, then use the "Resend Verification Email" button on the verification page.'],
                    ['q' => 'What if my child is under 13?',
                     'a' => 'Children under 13 cannot register independently. A guardian (18+) must register first, verify their email, then create a child account under their guardian account. This complies with COPPA (Children\'s Online Privacy Protection Act).'],
                    ['q' => 'How do I reset my password?',
                     'a' => 'On the login page, click "Forgot Password?" and enter your registered Gmail address. We\'ll send you a password reset link. The link expires in 60 minutes — if it expires, you can request a new one.'],
                    ['q' => 'What age groups does this platform serve?',
                     'a' => 'The platform serves learners aged 5 and up. Content is filtered by age bracket to ensure age-appropriate material. Children (5–12) require a guardian account. Teens (13–17) and adults (18+) can register independently.'],
                    ['q' => 'Can I go back and edit my registration information?',
                     'a' => 'During registration, you can navigate back to previous steps and your information will be saved automatically. After completing registration, you can update most profile details from your profile settings page.'],
                    ['q' => 'How do I change my username?',
                     'a' => 'Free account users can change their username once every 30 days from their profile settings. Premium subscribers can change it more frequently. Your username must be 3–30 characters using lowercase letters, numbers, underscores, or hyphens.'],
                    ['q' => 'What is a premium subscription?',
                     'a' => 'Premium subscribers unlock additional features including unlimited quiz attempts, certificate downloads, module attachment downloads, and priority support. You can upgrade from your account settings.'],
                ];
            @endphp

            @foreach($faqs as $i => $faq)
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <button @click="activeQ = activeQ === {{ $i }} ? null : {{ $i }}"
                            class="w-full flex items-center justify-between px-4 py-3.5 text-left text-sm font-medium text-gray-800 hover:bg-gray-50 transition-colors">
                        <span>{{ $faq['q'] }}</span>
                        <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform duration-200"
                             :class="activeQ === {{ $i }} ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="activeQ === {{ $i }}"
                         x-collapse
                         class="px-4 pb-4 text-sm text-gray-600 leading-relaxed border-t border-gray-100 pt-3">
                        {{ $faq['a'] }}
                    </div>
                </div>
            @endforeach

        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 shrink-0 flex justify-end">
            <button @click="open = false"
                    style="background: linear-gradient(135deg, #A30EB2, #730DB1, #3B0CB1);"
                    class="inline-flex items-center justify-center px-6 py-2.5 text-sm font-semibold text-white rounded-xl shadow-md hover:opacity-90 transition-all duration-200">
                Got it, thanks!
            </button>
        </div>
    </div>
</div>
