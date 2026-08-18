@php
    $canUseTextTranslator = (bool) ($canUseTextTranslator ?? false);
@endphp

<div id="cc-page-translator" class="fixed top-24 right-4 z-[9998]">
    <button type="button"
            id="cc-translator-toggle"
            class="w-11 h-11 rounded-full border {{ $canUseTextTranslator ? 'border-brand-200 bg-white text-brand-600 hover:bg-brand-50' : 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' }} shadow-lg hover:shadow-xl transition-all"
            title="Page Translator">
        <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 5.5A18.022 18.022 0 0015.588 9m-9.176 0a18.022 18.022 0 013.636 5.5m3.952 0A18.022 18.022 0 0117.588 9M13 21l3-9 3 9m-5.2-3h4.4" />
        </svg>
    </button>

    <div id="cc-page-translator-panel" class="hidden mt-2 w-[260px] rounded-2xl border border-brand-200 bg-white/95 shadow-xl backdrop-blur-sm p-3.5">
        <div class="flex items-center justify-between mb-2">
            <p class="text-[11px] font-bold uppercase tracking-wide text-brand-700">Page Translator</p>
            <button type="button" id="cc-restore-page-btn" class="text-[11px] font-semibold text-gray-500 hover:text-brand-700 transition-colors" @if(!$canUseTextTranslator) disabled @endif>Restore</button>
        </div>

        <div class="flex items-center gap-2">
            <select id="cc-translator-lang" class="flex-1 text-sm rounded-xl border border-gray-200 bg-gray-50 px-2.5 py-2 text-gray-800 focus:outline-none focus:ring-2 focus:ring-brand-200 focus:border-brand-200" @if(!$canUseTextTranslator) disabled @endif>
                <option value="en">English</option>
                <option value="tl">Tagalog</option>
            </select>
            <button type="button" id="cc-translate-page-btn" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white rounded-xl transition-colors bg-brand-500 hover:bg-brand-600 disabled:opacity-60 disabled:cursor-not-allowed" @if(!$canUseTextTranslator) disabled @endif>
                Translate
            </button>
        </div>

        @if(!$canUseTextTranslator)
            <div class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-2.5 py-2 text-[11px] text-amber-800">
                Page translation is available on premium learner plans.
                <a href="{{ route('subscription.index') }}" class="font-semibold underline">Upgrade to unlock</a>
            </div>
        @endif

        <div class="mt-2 flex items-center justify-between gap-2">
            <p id="cc-translator-status" class="flex-1 min-h-[16px] text-[11px] text-gray-500"></p>
            <span id="cc-translator-active-chip" class="inline-flex items-center rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 whitespace-nowrap">Voice: English</span>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__ccPageTranslatorLoaded) {
        return;
    }
    window.__ccPageTranslatorLoaded = true;

    document.addEventListener('DOMContentLoaded', function () {
        var wrapper = document.getElementById('cc-page-translator');
        if (!wrapper) {
            return;
        }

        var toggleBtn = document.getElementById('cc-translator-toggle');
        var panelEl = document.getElementById('cc-page-translator-panel');
        var translateBtn = document.getElementById('cc-translate-page-btn');
        var restoreBtn = document.getElementById('cc-restore-page-btn');
        var langSelect = document.getElementById('cc-translator-lang');
        var statusEl = document.getElementById('cc-translator-status');
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var apiUrl = @json(route('learner.translator.page'));
        var storageKey = 'cc_page_translation_language';
        var hasTextTranslator = @json($canUseTextTranslator);
        var languageChangeEventName = 'cc:translation-language-changed';
        var dictionaryCachePrefix = 'cc_page_translation_dictionary_v1:';
        var routeSnapshotPrefix = 'cc_page_translation_snapshot_v1:';
        var maxDictionaryEntries = 3000;
        var startupLanguage = window.__ccPreferredTranslationLanguage || null;
        var activeChip = document.getElementById('cc-translator-active-chip');
        var isBusy = false;

        var originalNodeText = new Map();

        function normalizeLanguage(language) {
            if (language === 'tl') {
                return 'tl';
            }

            return 'en';
        }

        function toSpeechLanguageCode(language) {
            if (language === 'tl') {
                return 'fil-PH';
            }

            return 'en-US';
        }

        function updateActiveChip(language) {
            if (!activeChip) {
                return;
            }

            if (language === 'tl') {
                activeChip.textContent = 'Voice: Tagalog';
                return;
            }

            activeChip.textContent = 'Voice: English';
        }

        function setTranslationPending(isPending) {
            if (!document || !document.documentElement) {
                return;
            }

            if (isPending) {
                document.documentElement.setAttribute('data-cc-translation-pending', '1');
                return;
            }

            document.documentElement.removeAttribute('data-cc-translation-pending');
        }

        function broadcastLanguageSelection(language) {
            window.dispatchEvent(new CustomEvent(languageChangeEventName, {
                detail: {
                    language: language,
                    voiceLanguageCode: toSpeechLanguageCode(language),
                }
            }));
        }

        function persistLanguageSelection(language, options) {
            var normalizedLanguage = normalizeLanguage(language);
            var shouldPersist = !options || options.persist !== false;
            var shouldBroadcast = !options || options.broadcast !== false;

            if (shouldPersist) {
                safeStorageSet(storageKey, normalizedLanguage);
            }

            if (document && document.documentElement) {
                document.documentElement.setAttribute('lang', normalizedLanguage);
            }

            if (normalizedLanguage === 'en') {
                setTranslationPending(false);
            }

            updateActiveChip(normalizedLanguage);

            if (shouldBroadcast) {
                broadcastLanguageSelection(normalizedLanguage);
            }
        }

        function setStatus(message, isError) {
            statusEl.textContent = message || '';
            statusEl.className = 'flex-1 min-h-[16px] text-[11px] ' + (isError ? 'text-red-600' : 'text-gray-500');
        }

        function collectTextNodes() {
            var nodes = [];
            var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
                acceptNode: function (node) {
                    if (!node || typeof node.nodeValue !== 'string') {
                        return NodeFilter.FILTER_REJECT;
                    }

                    var guardian = node.parentElement;
                    if (!guardian) {
                        return NodeFilter.FILTER_REJECT;
                    }

                    if (guardian.closest('#cc-page-translator')) {
                        return NodeFilter.FILTER_REJECT;
                    }

                    var tag = guardian.tagName;
                    if (['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'CODE', 'PRE', 'SVG', 'OPTION'].includes(tag)) {
                        return NodeFilter.FILTER_REJECT;
                    }

                    if (node.nodeValue.trim() === '') {
                        return NodeFilter.FILTER_REJECT;
                    }

                    return NodeFilter.FILTER_ACCEPT;
                }
            });

            while (walker.nextNode()) {
                nodes.push(walker.currentNode);
            }

            return nodes;
        }

        function chunk(array, size) {
            var output = [];
            for (var i = 0; i < array.length; i += size) {
                output.push(array.slice(i, i + size));
            }
            return output;
        }

        function safeStorageGet(key) {
            try {
                return localStorage.getItem(key);
            } catch (error) {
                return null;
            }
        }

        function safeStorageSet(key, value) {
            try {
                localStorage.setItem(key, value);
            } catch (error) {
                // Ignore quota/storage errors to keep translation functional.
            }
        }

        function safeParseJson(value) {
            if (typeof value !== 'string' || value.trim() === '') {
                return null;
            }

            try {
                return JSON.parse(value);
            } catch (error) {
                return null;
            }
        }

        function dictionaryStorageKey(language) {
            return dictionaryCachePrefix + language;
        }

        function routeSnapshotStorageKey(language) {
            var routeKey = window.location.pathname + window.location.search;
            return routeSnapshotPrefix + language + ':' + encodeURIComponent(routeKey);
        }

        function readDictionary(language) {
            var parsed = safeParseJson(safeStorageGet(dictionaryStorageKey(language)));
            var dictionary = new Map();

            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                return dictionary;
            }

            Object.keys(parsed).forEach(function (key) {
                var translatedValue = parsed[key];
                if (typeof translatedValue === 'string' && translatedValue.trim() !== '') {
                    dictionary.set(key, translatedValue);
                }
            });

            return dictionary;
        }

        function writeDictionary(language, dictionary) {
            var entries = Array.from(dictionary.entries());
            if (entries.length > maxDictionaryEntries) {
                entries = entries.slice(entries.length - maxDictionaryEntries);
            }

            var payload = {};
            entries.forEach(function (entry) {
                payload[entry[0]] = entry[1];
            });

            safeStorageSet(dictionaryStorageKey(language), JSON.stringify(payload));
        }

        function readRouteSnapshot(language) {
            var parsed = safeParseJson(safeStorageGet(routeSnapshotStorageKey(language)));
            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                return null;
            }

            if (typeof parsed.fingerprint !== 'string' || !parsed.translations || typeof parsed.translations !== 'object') {
                return null;
            }

            return parsed;
        }

        function writeRouteSnapshot(language, fingerprint, dictionary, sourceTexts) {
            var translations = {};
            sourceTexts.forEach(function (sourceText) {
                if (dictionary.has(sourceText)) {
                    translations[sourceText] = dictionary.get(sourceText);
                }
            });

            safeStorageSet(routeSnapshotStorageKey(language), JSON.stringify({
                fingerprint: fingerprint,
                updated_at: Date.now(),
                translations: translations,
            }));
        }

        function hashString(input) {
            var hash = 0;
            for (var i = 0; i < input.length; i++) {
                hash = ((hash << 5) - hash) + input.charCodeAt(i);
                hash |= 0;
            }

            return String(hash >>> 0);
        }

        function buildFingerprint(texts) {
            return hashString(texts.join('\n')) + ':' + texts.length;
        }

        function uniqueTrimmedTexts(nodes) {
            var seen = new Set();
            var unique = [];

            nodes.forEach(function (node) {
                if (!originalNodeText.has(node)) {
                    originalNodeText.set(node, node.nodeValue);
                }

                var original = originalNodeText.get(node);
                var trimmed = original.trim();
                if (!trimmed || seen.has(trimmed)) {
                    return;
                }

                seen.add(trimmed);
                unique.push(trimmed);
            });

            return unique;
        }

        function applyTranslations(nodes, dictionary) {
            var appliedCount = 0;

            nodes.forEach(function (node) {
                var original = originalNodeText.get(node);
                if (!original) {
                    return;
                }

                var trimmed = original.trim();
                if (!trimmed) {
                    return;
                }

                var translated = dictionary.get(trimmed);
                if (!translated) {
                    return;
                }

                var leading = (original.match(/^\s*/) || [''])[0];
                var trailing = (original.match(/\s*$/) || [''])[0];
                node.nodeValue = leading + translated + trailing;
                appliedCount += 1;
            });

            return appliedCount;
        }

        function mergeTranslationsIntoDictionary(dictionary, translations) {
            if (!translations || typeof translations !== 'object' || Array.isArray(translations)) {
                return;
            }

            Object.keys(translations).forEach(function (sourceText) {
                var translated = translations[sourceText];
                if (typeof translated === 'string' && translated.trim() !== '') {
                    dictionary.set(sourceText, translated);
                }
            });
        }

        async function requestBatchesParallel(textBatches, targetLanguage, concurrency, onProgress) {
            var results = new Array(textBatches.length);
            var completed = 0;

            for (var offset = 0; offset < textBatches.length; offset += concurrency) {
                var group = textBatches.slice(offset, offset + concurrency);
                await Promise.all(group.map(function (batch, groupIndex) {
                    var batchIndex = offset + groupIndex;
                    return requestBatch(batch, targetLanguage).then(function (translatedBatch) {
                        results[batchIndex] = translatedBatch;
                        completed += 1;
                        if (typeof onProgress === 'function') {
                            onProgress(completed, textBatches.length);
                        }
                    });
                }));
            }

            return results;
        }

        function restorePage() {
            originalNodeText.forEach(function (original, node) {
                if (node && node.isConnected) {
                    node.nodeValue = original;
                }
            });
            persistLanguageSelection('en');
            langSelect.value = 'en';
            setStatus('Original page restored.', false);
        }

        async function requestBatch(texts, targetLanguage) {
            var response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    texts: texts,
                    target_language: targetLanguage,
                }),
            });

            var payload = {};
            try {
                payload = await response.json();
            } catch (error) {
                payload = {};
            }

            if (!response.ok) {
                throw new Error(payload.message || 'Page translation failed.');
            }

            if (!Array.isArray(payload.translated_texts)) {
                throw new Error('Invalid translation response.');
            }

            return payload.translated_texts;
        }

        async function translatePage(targetLanguage) {
            targetLanguage = normalizeLanguage(targetLanguage);

            if (!hasTextTranslator) {
                setStatus('Upgrade to unlock page translation.', true);
                return;
            }

            if (targetLanguage === 'en') {
                restorePage();
                return;
            }

            if (isBusy) {
                return;
            }

            isBusy = true;
            translateBtn.disabled = true;
            setTranslationPending(true);
            setStatus('Scanning page text...', false);

            try {
                var nodes = collectTextNodes();
                if (!nodes.length) {
                    setStatus('No translatable text found.', true);
                    setTranslationPending(false);
                    return;
                }

                var uniqueTexts = uniqueTrimmedTexts(nodes);
                var fingerprint = buildFingerprint(uniqueTexts);
                var dictionary = readDictionary(targetLanguage);
                var snapshot = readRouteSnapshot(targetLanguage);

                if (snapshot && snapshot.fingerprint === fingerprint) {
                    mergeTranslationsIntoDictionary(dictionary, snapshot.translations);
                }

                var cachedHits = applyTranslations(nodes, dictionary);
                var missingTexts = uniqueTexts.filter(function (sourceText) {
                    return !dictionary.has(sourceText);
                });

                if (!missingTexts.length) {
                    persistLanguageSelection(targetLanguage);
                    writeDictionary(targetLanguage, dictionary);
                    writeRouteSnapshot(targetLanguage, fingerprint, dictionary, uniqueTexts);
                    setStatus(cachedHits ? 'Applied saved translation.' : 'Page translated.', false);
                    setTranslationPending(false);
                    return;
                }

                setStatus(cachedHits ? 'Refreshing translation...' : 'Translating page...', false);

                var textBatches = chunk(missingTexts, 120);
                var translatedBatchResults = await requestBatchesParallel(textBatches, targetLanguage, 3, function (done, total) {
                    setStatus('Translating page... ' + done + '/' + total, false);
                });

                textBatches.forEach(function (sourceBatch, batchIndex) {
                    var translatedBatch = translatedBatchResults[batchIndex] || [];
                    sourceBatch.forEach(function (sourceText, index) {
                        dictionary.set(sourceText, translatedBatch[index] || sourceText);
                    });
                });

                applyTranslations(nodes, dictionary);
                persistLanguageSelection(targetLanguage);
                writeDictionary(targetLanguage, dictionary);
                writeRouteSnapshot(targetLanguage, fingerprint, dictionary, uniqueTexts);
                setStatus(cachedHits ? 'Translation updated.' : 'Page translated.', false);
                setTranslationPending(false);
            } catch (error) {
                setStatus(error.message || 'Unable to translate the page right now.', true);
                setTranslationPending(false);
            } finally {
                isBusy = false;
                translateBtn.disabled = false;
            }
        }

        toggleBtn.addEventListener('click', function () {
            panelEl.classList.toggle('hidden');
        });

        document.addEventListener('click', function (event) {
            if (!wrapper.contains(event.target)) {
                panelEl.classList.add('hidden');
            }
        });

        translateBtn.addEventListener('click', function () {
            if (!hasTextTranslator) {
                setStatus('Upgrade to unlock page translation.', true);
                return;
            }

            translatePage(langSelect.value);
        });

        langSelect.addEventListener('change', function () {
            if (!hasTextTranslator) {
                return;
            }

            translatePage(langSelect.value);
        });

        restoreBtn.addEventListener('click', function () {
            restorePage();
        });

        var savedLanguage = normalizeLanguage(startupLanguage || safeStorageGet(storageKey) || 'en');
        if (savedLanguage && langSelect.querySelector('option[value="' + savedLanguage + '"]')) {
            langSelect.value = savedLanguage;
        }

        persistLanguageSelection(savedLanguage, {
            persist: false,
        });

        if (!hasTextTranslator) {
            langSelect.value = 'en';
            setStatus('Upgrade to unlock page translation.', true);
            persistLanguageSelection('en');
            return;
        }

        if (savedLanguage !== 'en') {
            requestAnimationFrame(function () {
                translatePage(savedLanguage);
            });
        }
    });
})();
</script>
