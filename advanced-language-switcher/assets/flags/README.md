# SVG flags

The Elementor widget's **SVG Flag** option looks for a file named after the
language's ISO country code in this folder:

    assets/flags/us.svg
    assets/flags/es.svg
    assets/flags/sa.svg

Drop your own SVG files here using that naming convention and they are picked
up automatically — no code change and no plugin update needed.

If a file is missing, the switcher falls back to the emoji flag, so a partial
set is safe. For per-language artwork that does not follow the country-code
convention, set a **Custom Flag Image URL** on the language itself and choose
the widget's **Image Flag** option instead.

No flags are bundled: country flags are political artwork with licensing and
accuracy considerations, and a wrong or outdated flag is worse than none. The
emoji flags used by default are rendered by the operating system and are always
current.
