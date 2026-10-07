# Ground rules for this project

- **All work happens on the test site only:** the WordPress install at
  https://lightgoldenrodyellow-skunk-967361.hostingersite.com (credentials: WP_USER / WP_APP_PASSWORD).
- **Never touch the live store**: the site on swedishfikalb.com, built with Hostinger Website Builder, which
  customers are buying from. Do not change anything that could affect it, including:
  - the swedishfikalb.com domain, its DNS records (SPF, DKIM, DMARC, MX, A, CNAME) or domain settings;
  - the Website Builder site, its pages, forms, store or settings;
  - the hello@swedishfikalb.com mailbox: no password changes, forwarding, filters, webhooks, sending or deleting
    (reading the mailbox address was the only access so far);
  - anything in the Hostinger account outside the test WordPress install.
- Pointing swedishfikalb.com at the new site is a separate launch step, planned with the owner first.
- Test data (customers, orders, coupons) uses @example.com addresses and the note
  "TEST ORDER (Claude ... test) - safe to delete", and is deleted after each test.
