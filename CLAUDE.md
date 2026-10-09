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
