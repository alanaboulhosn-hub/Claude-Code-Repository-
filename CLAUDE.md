# Ground rules for this project

- **The shop is live** since 2026-10-09: the WordPress install at https://swedishfikalb.com (credentials:
  WP_USER / WP_APP_PASSWORD). Real customers order from it. The old temporary address
  (lightgoldenrodyellow-skunk-967361.hostingersite.com) is retired.
- **Changes on the live shop:** small changes are made directly and checked right after on phone and desktop;
  bigger changes are tested first (Hostinger's WordPress staging copy if needed). Check the live copy of a snippet
  or page matches git before replacing it. The site firewall refuses some PUT updates: update snippets with POST.
- **No test orders on the live shop unless needed**, and only after telling the owner: they reach the owner's
  "New order" emails and Meta (the pixel is live). Prefer tests that place no order. Meta beacons from test
  browsers are blocked (route /fika/v1/meta and facebook).
- **Never touch, without the owner:**
  - the swedishfikalb.com domain, its DNS records (SPF, DKIM, DMARC, MX, A, CNAME) or domain settings;
  - the old Website Builder site, its pages, forms, store or settings;
  - the hello@swedishfikalb.com mailbox: no password changes, forwarding, filters, webhooks, sending or deleting;
  - anything in the Hostinger account outside the WordPress install.
- Test data (customers, orders, coupons) uses @example.com addresses and the note
  "TEST ORDER (Claude ... test) - safe to delete", and is deleted after each test.
- **Never send a customer the same email twice.** The owner would rather a customer gets no email than two. Any
  sending code marks the email as sent before it goes out (a failed or cut-off send is not retried), runs one at a
  time, and a stopped send is never restarted until it is clear who already got it.
- **Email changes:** after any change to an email, show the owner the whole email (rendered from the shop's real
  email code, never sent), not just the part that changed: republish the "Fika Win-back Email" artifact
  (https://claude.ai/artifact/VtpguUmXoMcStVCDV1ZwHr, phone / computer switch) and open it in the side panel.
- **Site check (owner, 2026-11-16):** after any change to the live site that could affect pages, the bag or checkout
  (snippets, pages, patterns, WooCommerce or plugin settings, plugin/theme/WordPress updates, cache), run
  `node wordpress/tools/site-check.js` and report the result; a FAIL is fixed or undone before the work counts as done.
  It also runs every night at midnight Beirut time (scheduled task "Fika nightly site check"). It places no order and
  sends no email. When it reports software updates and passes, record them in wordpress/tools/site-check-versions.json.
