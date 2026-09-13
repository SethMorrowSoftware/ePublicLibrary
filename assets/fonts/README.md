# Fonts

## Shipped with the app (tracked in git)

The library, admin, and reader UIs use two SIL Open Font License 1.1
families, served from this directory. They are **committed to the
repository** so a fresh install renders correctly with no third-party
requests, no CSP exceptions, and full offline support through the service
worker.

| File | Family | Subset |
|---|---|---|
| `dm-sans-latin.woff2`                | DM Sans (variable 300–700) | latin |
| `dm-sans-latin-ext.woff2`            | DM Sans (variable 300–700) | latin-ext |
| `instrument-serif-latin.woff2`       | Instrument Serif 400       | latin |
| `instrument-serif-latin-ext.woff2`   | Instrument Serif 400       | latin-ext |
| `instrument-serif-italic-latin.woff2`| Instrument Serif 400 italic| latin |

The `@font-face` declarations live at the top of
`assets/css/design-system.css`. Both families are © their respective
authors and licensed under the SIL Open Font License 1.1:

- DM Sans — Colophon Foundry, Jonny Pinhorn, Indian Type Foundry
- Instrument Serif — Instrument

To swap in different families, replace the files here and edit the
`@font-face` blocks plus the `--font-display` / `--font-body` tokens.

## Optional: OpenDyslexic (not tracked)

The reader offers **OpenDyslexic** in its font-family picker. That font is
*not* bundled — drop the two `.woff2` builds in here and the option starts
working. Without them, the browser silently falls back to the next family
in the stack (Iowan Old Style → Charter → serif).

| File | Source |
|---|---|
| `OpenDyslexic-Regular.woff2` | https://opendyslexic.org/ → Downloads → WOFF2 release |
| `OpenDyslexic-Bold.woff2`    | same release |

```bash
cd assets/fonts/
curl -L -o OpenDyslexic-Regular.woff2 \
  https://github.com/antijingoist/opendyslexic/raw/master/compiled/OpenDyslexic-Regular.woff2
curl -L -o OpenDyslexic-Bold.woff2 \
  https://github.com/antijingoist/opendyslexic/raw/master/compiled/OpenDyslexic-Bold.woff2
```

OpenDyslexic is also SIL OFL 1.1 — include `OFL.txt` from the upstream
release if you redistribute it alongside this app.
