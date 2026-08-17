=== Advanced Language Switcher ===
Contributors: advancedlanguageswitcher
Tags: multilingual, translation, elementor, woocommerce, language switcher
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A complete multilingual translation management system with a deeply customizable Elementor language switcher, pluggable translation engines and WooCommerce support.

== Description ==

Advanced Language Switcher turns a single-language WordPress site into a multilingual one. Visitors pick a language from a switcher you place anywhere with Elementor, and **the whole site** changes — header, menus, content, footer, WooCommerce, forms and theme text — not just one section.

It is built as a translation management system rather than a machine-translation button:

WordPress Admin → Language Manager → Translation Database → Translation Engine → Translation Cache → WordPress / Elementor / WooCommerce → Frontend Language Switcher

= What it does =

* **Language manager.** Add, edit, duplicate, reorder, enable, disable and delete languages. Set names, native names, short codes, locales, countries, flags and text direction.
* **Translation memory.** Source strings and their translations live in dedicated, properly indexed database tables, with context so the same word can be translated differently in different places.
* **Works with no API key.** The Built-in Translator is the default engine and needs no account, no key and no setup. Google, DeepL and OpenAI remain available as optional upgrades behind the same interface, and adding another engine is a single filter.
* **Translates by itself.** New text is picked up as visitors browse and translated in the background, after the page has already been delivered, so page speed is untouched.
* **Whole-site translation.** WordPress switches to the target locale, gettext strings resolve against your own translation memory, and a full page pass rewrites everything else — Elementor widgets, menus, WooCommerce templates, forms and theme output.
* **Elementor widget.** A Language Switcher widget in its own "Language Translator" category, with eight presets and complete Content, Style and Advanced controls, including hover and active states and responsive values on every important control.
* **WooCommerce.** Products, categories, attributes, variations, cart, checkout and account pages translate. SKUs, prices, order numbers and internal IDs never do. The cart survives a language change.
* **SEO.** hreflang alternates including x-default, language-correct canonicals, and meta translation for Yoast SEO, Rank Math and All in One SEO.
* **Safety.** If a translation is missing or a provider fails, the original text renders. A page can never go blank because of this plugin.

= Requirements =

* WordPress 6.0 or newer
* PHP 8.1 or newer
* Elementor 3.0 or newer for the widget (everything else works without it)
* WooCommerce for the shop integration (optional)

== Installation ==

1. Upload the `advanced-language-switcher` folder to `/wp-content/plugins/`, or install the ZIP through **Plugins → Add New → Upload Plugin**.
2. Activate the plugin. Database tables, default settings and a default language matching your site locale are created automatically. No website scan runs at this point, so activation stays fast.
3. Go to **Language Translator → Languages** and add the languages you want to offer.
4. Go to **Language Translator → URL Settings** and choose a URL structure. Directory URLs (`example.com/es/`) are recommended.
5. Go to **Language Translator → Dashboard** and press **Scan Website**.
6. Open **Language Translator → Translations** and press **Translate Missing Only**. No API key is needed: the Built-in Translator is already active.
7. Review the results and edit anything you want to word differently. Your edits are never overwritten.
8. Edit your Elementor header, drag in the **Language Switcher** widget, style it and publish.

== Configuration ==

= Adding languages =

Each language has a name, a native name, a short code used in URLs and in the switcher, a WordPress locale, a country for the flag, and a direction. Choosing a language from the built-in catalogue fills all of these in; every value stays editable.

The **locale** matters: it is what WordPress uses to load core, theme and plugin language packs, which is where a large part of the interface translation comes from for free.

Exactly one language is the default. It is the source language for translation and cannot be disabled or deleted; promote another language first if you need to remove it.

= URL structures =

* `example.com/` and `example.com/es/` — directory URLs with no prefix on the default language. Recommended.
* `example.com/en/` and `example.com/es/` — a prefix for every language.
* `example.com/?lang=es` — query parameter, works without pretty permalinks.
* Cookie only — no distinct URL per language. Search engines will only index one version.

The active language is resolved from the URL, then the cookie, then the logged-in user preference, then browser detection (if enabled), then the site default. An explicit choice always beats browser detection, and crawlers are never redirected.

= Translation engines =

**No API key is required.** The plugin ships with a Built-in Translator that is
selected out of the box, so translation works the moment you add a language.

It is worth being precise about what "built-in" means. Real machine translation
needs a language model far larger than any plugin can carry, so the built-in
engine calls a free public service rather than translating offline. What it
removes is the account and the API key, not the network request. Two services
are supported:

* **MyMemory** (default) — free, keyless, with a daily character allowance.
  Nothing to configure. Adding an optional contact e-mail on the Translation
  Engine screen raises that allowance; it is not a key or an account.
* **LibreTranslate** — open source and self-hostable. Point the plugin at your
  own server for unlimited translation where no third party ever sees your
  content.

Because every result is written to the translation memory, a given string
costs one request in the lifetime of the site. A page that has been translated
once never contacts the service again, so the daily allowance is spent on new
content only.

Google Translate, DeepL and OpenAI remain available for sites that want them.
Those do need a key; API keys are stored in a separate, non-autoloaded option,
are readable only by administrators, are masked in the interface and are never
exposed to front end JavaScript.

= Automatic background translation =

With the defaults, untranslated text is translated on its own:

1. A visitor opens a page in a non-default language. It renders immediately,
   using the original text for anything not yet translated.
2. After the response has been sent, the plugin records the new strings and
   translates a small batch of them.
3. The next visitor to that page sees it translated.

Nothing is translated while the visitor waits, so this never slows a page down.
A site converges over the first few views; pressing **Translate Missing Only**
on the Translations screen does the whole site at once instead.

Batch size and the whole behaviour are configurable under **Translation
Engine → Automatic Translation**, and the background pass is lock-guarded so a
traffic spike cannot turn into a burst of translation requests.

Before a string is sent to a provider, placeholders are masked: `{name}`, `%name%`, `{{product_name}}`, `[shortcode]`, printf placeholders, URLs, e-mail addresses and HTML entities are replaced with opaque tokens and restored afterwards, so merge tags and shortcodes cannot be mangled. HTML structure is preserved; only visible text is translated.

Glossary terms, defined per language, always win over whatever a provider returns.

= Performance =

Translation follows `translation → cache → render`, never `page → API → translate`. The dictionary for a language is loaded once per request and cached; individual strings are never fetched one at a time, and the external API is never called for a string that has already been translated.

Optional full page caching stores the translated HTML per URL and language. It is skipped automatically for logged-in users, search results, POST requests and WooCommerce cart, checkout and account pages.

== Frequently Asked Questions ==

= Does this translate my WordPress dashboard too? =

No. The plugin translates the public website only. Everything behind wp-admin —
menus, post editor, settings screens, WooCommerce order screens, the Elementor
editor — stays in whatever language you set for WordPress itself under
Settings → General (or per user under Users → Profile → Language).

That separation is deliberate and enforced in one place: admin screens,
admin-ajax requests made from wp-admin, and REST requests made by the block
editor all resolve to "no translation", so the locale is never switched and no
content filter runs. Front end AJAX (WooCommerce add-to-cart, for example) is
correctly identified as front end and still translates.

It also means an editor always sees the original text when editing a post or a
product, so a translation can never be saved over your source content.

= Does the whole site translate, or only the Elementor widget? =

The whole site. The Elementor widget is only the control that changes the active language; the translation layer operates globally across WordPress, Elementor and WooCommerce.

= What happens if a page has no translation? =

The original text renders. That is the default fallback and it applies at every level: a missing string, a missing page, or a failing translation API. You can switch the page-level fallback to "redirect to the translated home page" under URL Settings, but showing the original page is recommended so a visitor never loses their place.

= Do I need an API key or a paid account? =

No. The Built-in Translator is active by default and needs neither. Google,
DeepL and OpenAI are optional and only appear as choices; leaving them alone
costs nothing.

= What happens when the free daily allowance runs out? =

Everything already translated keeps working, because translations are stored in
your own database rather than fetched per request. Strings that were not
reached stay queued and resume the next day. You can raise the allowance with
an optional contact e-mail, switch to your own LibreTranslate server for no
limit, or translate the remainder by hand.

= What happens if a translation service is down or my host blocks it? =

Nothing visible to visitors: the original text keeps rendering. The failure is
logged (when logging is enabled) and reported in the admin. Use **Test
Connection** on the Translation Engine screen to check whether your host allows
outbound HTTP requests at all.

= Will this empty my WooCommerce cart? =

No. Switching language changes only the rendering context. The session cookie, cart items, quantities and customer data are untouched.

= Can I use it without Elementor? =

Yes. Use the shortcode `[als_language_switcher]` or the template function `als_language_switcher()`. Only the widget requires Elementor.

= Does it work on multisite? =

Yes, with per-site language configuration. Tables and defaults are provisioned for each site, including sites created after activation.

= Are prices and SKUs translated? =

No. WooCommerce prices, currency symbols, SKUs and order numbers are excluded automatically. Add more CSS classes under Translation Settings → Exclusions if your theme uses different markup.

= How do I stop a specific block of content from being translated? =

Add a `no-translate` class to it, or the standard HTML attribute `translate="no"`. Both are honoured, along with any additional classes you configure.

== Troubleshooting ==

**Languages appear but URLs 404.** Directory URL mode requires pretty permalinks. Visit Settings → Permalinks and save once, or switch to query parameter mode.

**Translations are not appearing on the front end.** Confirm the language is enabled, that strings exist for it (Language Translator → Translations), and that the relevant scope is on under Translation Settings. Clear the cache from Language Translator → Cache after a bulk edit.

**Automatic translation says "no API key".** Save the key on the Translation Engine screen and press Test Connection.

**A rate limit error appears during bulk translation.** Lower the batch size under Translation Settings → Automatic Translation.

**The widget is missing in Elementor.** Check Language Translator → System Status for the Elementor row, and look under the "Language Translator" widget category.

**Some theme text stays in the source language.** That text is likely produced without gettext and outside the page body pass, or it lives inside an excluded element. Run a scan, then search for it in the translation editor.

== Developer Reference ==

= Functions =

`als_get_languages( $include_disabled = false )`
`als_get_current_language()`
`als_get_current_language_code()`
`als_get_default_language()`
`als_is_default_language()`
`als_translate( $text, $language = '', $context = '' )`
`als_translate_html( $html, $language = '' )`
`als_get_translation( $text, $language, $context = '' )`
`als_is_rtl()`
`als_get_language_url( $language, $url = '' )`
`als_get_alternate_urls( $url = '' )`
`als_language_switcher( $args = array(), $echo = true )`
`als_register_string( $text, $context = '' )`
`als_get_translation_stats()`

= Actions =

* `als_loaded` — every subsystem has registered its hooks. Receives the plugin container.
* `als_before_translate` — before a string is translated. Receives text, language, context.
* `als_after_translate` — after a string is translated. Receives translation, original, language.
* `als_translation_saved` — a translation was stored.
* `als_string_registered` — a new translatable string was discovered.
* `als_language_created`, `als_language_updated`, `als_language_deleted`, `als_default_language_changed`.
* `als_settings_updated` — settings were saved.

= Filters =

* `als_supported_languages` — the languages exposed to visitors.
* `als_current_language` — the resolved language for the request.
* `als_translation_string` — the final translated string.
* `als_pre_translate` — return a string to short-circuit translation.
* `als_is_translatable_string` — whether a string enters the translation memory.
* `als_translation_providers` — register a translation engine.
* `als_provider_language_code` — the language code sent to a provider.
* `als_placeholder_patterns` — the patterns protected during translation.
* `als_excluded_classes` — CSS classes that switch translation off for a subtree.
* `als_should_translate_page` — whether the full page pass runs.
* `als_translated_page` — the fully translated page markup.
* `als_page_cache_allowed` — whether the translated page may be cached.
* `als_language_url`, `als_alternate_urls` — URL building.
* `als_hreflang_output` — the hreflang block.
* `als_switcher_html`, `als_switcher_classes` — switcher markup.
* `als_script_data` — the data object handed to the front end script.
* `als_scanner_steps`, `als_scannable_post_types`, `als_elementor_key_translatable`, `als_theme_strings` — scanner behaviour.

= Registering a translation engine =

    add_filter( 'als_translation_providers', function ( $providers ) {
        $providers['my_engine'] = new My_Engine_Provider();

        return $providers;
    } );

`My_Engine_Provider` implements `ALS\Providers\Translation_Provider_Interface`. Caching, batching, placeholder protection and the glossary are handled for you.

= JavaScript API =

    window.ALS.currentLanguage;                  // active language code
    window.ALS.languages;                        // enabled languages
    window.ALS.getLanguageUrl( 'es' );           // this page in Spanish
    window.ALS.setLanguage( 'es' );              // switch language
    window.ALS.translate( 'Welcome', 'es' );     // Promise<string>

    document.addEventListener( 'als_language_changed', function ( event ) {
        console.log( event.detail.language, event.detail.direction );
    } );

= REST endpoints =

* `GET  /wp-json/als/v1/languages` — enabled languages and their URLs.
* `POST /wp-json/als/v1/translate` — translate one or more strings from the stored memory.
* `POST /wp-json/als/v1/switch` — record a language choice, returns the target URL.
* `GET  /wp-json/als/v1/stats` — translation statistics (requires the management capability).
* `GET  /wp-json/als/v1/strings` — translation editor data (requires the translation capability).
* `PUT  /wp-json/als/v1/strings/<id>` — save a translation (requires the translation capability).

= Capabilities =

* `manage_options` — languages, settings, API keys, cache, import and export.
* `als_edit_translations` — the translation editor and glossary. Granted to administrators and editors on activation.

== Security ==

* Every AJAX and REST write checks a nonce and a capability.
* Every database query is prepared.
* Output is escaped at the point of rendering.
* API keys live in a non-autoloaded option, are masked in the interface and never reach front end JavaScript.
* Provider error messages are shown only to administrators and never to visitors.

== Screenshots ==

1. Dashboard with translation activity per language.
2. Language manager.
3. Translation editor with search, filters and bulk automatic translation.
4. Elementor widget with preset designs and full style controls.
5. System status.

== Changelog ==

= 1.0.0 =
* Initial release.
* Language manager with flags, codes, locales, RTL support, ordering and duplication.
* Translation memory with context, statuses, glossary, backups and import/export in JSON, CSV, PO and MO.
* Manual, Google Translate, DeepL and OpenAI engines behind a pluggable adapter interface.
* Whole-site translation across WordPress, Elementor and WooCommerce.
* Elementor Language Switcher widget with eight presets and full Content, Style and Advanced controls.
* Directory, query parameter and cookie URL modes with hreflang and canonical support.
* Multi-tier caching, chunked website scanner, optional debug logging and a system status screen.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
