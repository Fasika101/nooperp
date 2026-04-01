Place your site logo here for the home page (and anywhere else you use asset('images/...')).

Welcome page display order (first file found wins):
  1. logo.svg
  2. logo.png
  3. logo.webp

Social / link previews (Open Graph) use RASTER images only (Telegram does not use SVG for previews):
  logo.png, logo.jpg / logo.jpeg, logo.webp
Add at least logo.png or logo.jpg for Telegram and other apps. About 1200x630 px is ideal.
If you only have logo.svg on disk, the page still shows it, but Telegram may show no preview image.

Telegram caches previews: after changing images/meta, try @WebpageBot or share the URL with ?v=2 once.

If previews were empty on Telegram only: the app serves a minimal HTML page to Telegram’s crawler (same meta tags, no huge CSS). Ensure logo.png or jpg exists for the image.

After adding a file, open the site root URL — the welcome page will show it automatically.

URL in the browser will look like: /images/logo.svg
