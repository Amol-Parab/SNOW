# OzMoneyTalks Tools (WordPress plugin)

Four tools for Indian migrants in Australia, each added to a page with a shortcode:

| Tool | Shortcode | What it does |
|---|---|---|
| #1 Settling-in checklist | `[oz_settling_checklist]` | Checklist for each visa situation (student, skilled, partner, visiting parent). Ticks are saved in the browser, and readers can have the list emailed to them. |
| #4 Rent move-in cost | `[oz_rent_calculator]` | Upfront cash needed to move in (bond, rent in advance, furniture, setup), with state defaults and a check against the 30% rent-to-income rule. |
| #3 India vs Australia savings | `[oz_savings_compare]` | Australian savings account vs NRE FD vs NRO FD after Indian tax, Australian tax (depends on visa), the treaty offset, transfer costs and rupee movement. |
| #11 Rate alert + weekly email | `[oz_rate_alert]` | Live AUD→INR rate with a 30-day chart. Readers set a target rate and get one email when it's reached, plus an optional weekly update. |

Every tool ends with an email signup, and all signups go into one list. You can also place a signup box anywhere with
`[oz_signup source="other" title="…" text="…"]`.

## Install

1. Zip the `ozmoneytalks-tools` folder (`zip -r ozmoneytalks-tools.zip ozmoneytalks-tools -x '*/tests/*'`).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, upload the zip and activate it.
3. Create one page per tool and paste its shortcode into a Shortcode block.
   - Two-column layout: the tools switch to form-beside-results when they have at least 760px of width. In a narrow content column they stack instead. For the two-column layout, put the shortcode inside a Group block set to **Wide width**.
   - Starting the checklist on a persona: `[oz_settling_checklist persona="skilled"]`, or link to the page with `?persona=parent`.
   - Starting the rent calculator on a state: `[oz_rent_calculator state="VIC"]`.
4. Go to **Settings → OzMoneyTalks Tools**, paste each page's URL (the tools and emails link to each other using these), set your brand colour and save.
5. **Email delivery:** install an SMTP plugin (e.g. WP Mail SMTP) with a transactional sender (Brevo, Mailgun, Amazon SES). Without it, confirmation emails will often land in spam. Use **Send me a test weekly email** on the settings page to check.
6. **Scheduled jobs:** WP-Cron only runs when someone visits the site. For reliable 8am alerts, ask your host to add a real cron job that calls `https://yoursite/wp-cron.php` every 15 minutes.
7. Your host must allow outgoing HTTPS requests to `api.frankfurter.dev`. The settings page shows whether the rate is loading.

## Connecting the remittance comparator (#2)

That tool was built in a separate chat and isn't in this repo. To connect it:

- Put its page URL in **Settings → Remittance comparator**. The checklist, rate alert, weekly email and alert emails will then link to it.
- Add `[oz_signup source="remittance" title="Get the weekly rate update"]` under it so its signups join the same list.
- It can use this plugin's cached rate instead of calling Frankfurter from every visitor's browser:
  `GET /wp-json/oz-tools/v1/rate` → `{ "rate": 57.83, "date": "2026-09-25", "history": [["2026-08-17", 57.78], …], "stale": false }`

## Keeping figures current — do this every 1 July

All the numbers that change over time are in **`includes/config.php`**:

- Australian tax brackets and Medicare levy (currently FY2026–27)
- Indian TDS on NRO interest and the India–Australia treaty rate
- Bond and rent-in-advance defaults by state, with links to each tenancy authority
- Default setup and furniture costs, and savings calculator starting values

After checking them, update `'reviewed'`. That date appears on every tool, so readers can see how current the figures are.

The checklist content is in **`includes/checklist-data.php`**. You can reword or add items, but don't change an existing item's `id`, because readers' saved ticks are stored against it.

Both files can also be overridden from a theme or mu-plugin using the `oz_tools_config` and `oz_tools_checklist` filters, so plugin updates won't overwrite your changes.

## Email list details

- **Double opt-in.** Readers must click a confirmation link, which keeps the list compliant with the Spam Act (consent, sender identified, unsubscribe in every email).
- **Confirm and unsubscribe need a button click on a page.** Email security scanners open links automatically, so a plain link visit changes nothing.
- **"Email me this checklist"** sends the checklist only. Readers join the list only if they tick the consent box.
- **Rate alerts are one-shot.** After an alert is sent, the target is cleared and the email links back to set a new one.
- **Spam protection:** a hidden honeypot field, 10 requests per hour per IP, and at most one confirmation email per 10 minutes per address.
  - Behind Cloudflare or another proxy, every visitor may appear to have the same IP. If so, pass the real IP with the `oz_tools_client_ip` filter.
- **Weekly email:** sent on your chosen day at around 8am site time, in batches of 40. It includes the rate, the week's change, the 30-day range, posts from the past week and tool links.
- **Admin:** subscriber counts, the latest signups and a CSV export are on the settings page. The plugin also works with WordPress's **Tools → Export/Erase Personal Data**.
- **Moving to Mailchimp or MailerLite later:** hook `oz_tools_subscriber_confirmed` ( `$email`, `$row` ) to push confirmed subscribers to that service.

## Analytics

If GA4 (`gtag`) or Google Tag Manager (`dataLayer`) is on the site, these events are sent:

| Event | When | Parameters |
|---|---|---|
| `oz_tool_use` | first input change in a calculator | `tool` |
| `oz_checklist_persona` | persona switched | `persona` |
| `oz_checklist_tick` | item ticked | `item`, `persona` |
| `oz_signup` | successful signup | `tool`, `consent`, `alert` |

## Tests

```
node --test ozmoneytalks-tools/tests/calc.test.js
```

The tests cover the rent, affordability, savings/tax, break-even and rate-history maths, with expected values worked by hand in the test comments.

## Uninstalling

Deleting the plugin (not just deactivating it) removes its settings and **the subscriber table**. Export the CSV first.
