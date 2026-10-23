Browsable copy of the shop with the Halloween look on (artifact https://claude.ai/artifact/8SfaHnfkF4c9j6tnwy2GqB).
Made in a test browser only: python3 wordpress/snippets/build-halloween.py --preview <dir> (hw.css, hw.js), then
node capture.js <site> hw.css hw.js desk|phone (each page with the look injected, frozen to HTML, scripts removed,
assets downloaded), then python3 assemble.py <site> (local asset paths, links between preview pages, a small helper for
the header, phone menu, bag icon and a "Preview" chip). Phone (m-*.html) and computer pages; it opens the one that fits.
