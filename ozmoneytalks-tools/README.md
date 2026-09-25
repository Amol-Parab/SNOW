# OzMoneyTalks Tools (WordPress plugin)

Five tools for Indian migrants in Australia, each added to a page with a shortcode:

| Tool | Shortcode | What it does |
|---|---|---|
| #2 Send money to India | `[oz_remittance]` | Ranks transfer providers by how many rupees the recipient gets, using the live mid-market rate and your provider fees and margins. Readers can add a quote they've been given. Shows the yearly cost of picking the wrong provider. |
| #1 Settling-in checklist | `[oz_settling_checklist]` | Checklist for each visa situation (student, skilled, partner, visiting parent). Ticks are saved in the browser, and readers can have the list emailed to them. |
| #4 Rent move-in cost | `[oz_rent_calculator]` | Upfront cash needed to move in (bond, rent in advance, furniture, setup), with state defaults and a check against the 30% rent-to-income rule. |
| #3 India vs Australia savings | `[oz_savings_compare]` | Australian savings account vs NRE FD vs NRO FD after Indian tax, Australian tax (depends on visa), the treaty offset, transfer costs and rupee movement. |
| #11 Rate alert + weekly email | `[oz_rate_alert]` | Live AUD→INR rate with a 30-day chart. Readers set a target rate and get one email when it's reached, plus an optional weekly update. |

Every tool ends with an email signup, and all signups go into one list. You can also place a signup box anywhere with
`[oz_signup source="other" title="…" text="…"]`.

There is also an **Ask helper**: a floating "Ask a question" box on every page that points readers to the right tool or post (see below).

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

## Ask helper

Turn it on under **Settings → OzMoneyTalks Tools → Ask helper**, after the tool page URLs are set. It is off by default.

**What it does.** For each question it shows, in this order:
1. **Your own answer**, if the question contains one of its keywords. You write these under **Ask helper: your answers**.
2. **Today's AUD→INR rate**, if the reader asks for it (for example "today's rate" or "AUD to INR").
3. **Up to two tools** that fit the question.
4. **Up to three posts or pages** that match the question's words.

If it finds nothing, it says so and lists all the tools.

**What it isn't.** It isn't an AI chatbot. It calls no outside service, needs no API key and costs nothing to run. It never writes financial content of its own, so it can't invent a wrong tax figure. The trade-off is that it only finds what's already on your site. It can't reason through a reader's situation.

**Personal advice.** When a question asks what the reader should do ("should I…", "which is better…", "recommend…"), the helper still shows the tools, but first says it can't give personal financial advice and points to a licensed adviser or registered tax agent. Every reply carries the "General information only" line.

**Improving it.** The **Ask helper: recent questions** table keeps the last 300 questions and what was found for each. Questions that found **nothing** are shown in red. Those are your best leads for new answers and new posts. The log keeps no names or IP addresses, and it strips emails and long numbers (phone, TFN, account). The helper tells readers that questions are saved. Add a line about this to your privacy policy, or turn the log off.

**Changing what it matches.** Tool keywords, the phrases that count as asking for advice, the starter buttons and the greeting are all in **`includes/chat-data.php`**. You can also override them with the `oz_tools_chat_data` filter.

Other hooks:
- `oz_tools_chat_show` (bool): return false to hide the helper on some pages.
- `oz_tools_chat_post_types` (array): which post types it searches (default `post`, `page`).
- `oz_tools_chat_hourly_limit` (int): questions per visitor per hour (default 30). This uses the same `oz_tools_client_ip` filter as signups.
- `oz_tools_chat_reply` (`$reply`, `$message`): change the reply before it's sent. This is the place to connect an AI model later. It receives the answer, the tools and the matching posts, so a model can write a summary grounded in your own content. If you do this, keep the advice notice and disclaimer. Also check the provider's data terms: some free tiers use the questions for training.

## Remittance providers — check at least monthly

The provider list is edited under **Settings → OzMoneyTalks Tools → Money transfer providers**, with no code changes needed.

For each provider, get a live quote for sending A$1,000 to India and enter:
- **Fixed fee (A$)** and/or **Fee %** — what they charge on top.
- **FX margin %** — how far their rate is below the mid-market rate. Example: mid-market ₹58.00, their rate ₹57.42 → 1%. A promo rate above mid-market is a negative margin.
- **Link** and **Affiliate?** — affiliate links get `rel="sponsored"`, and the page shows a disclosure line.
- **Figures last checked** — shown to readers. Until you set it, the page says the costs are estimates.

The defaults that ship with the plugin are rough estimates, not checked figures. Replace them before launch. Rows are always sorted by what the recipient gets, never by the order you enter them.

Other code can use the plugin's cached rate: `GET /wp-json/oz-tools/v1/rate` → `{ "rate": 57.83, "date": "2026-09-25", "history": [["2026-08-17", 57.78], …], "stale": false }`

## Keeping figures current — do this every 1 July

All the numbers that change over time are in **`includes/config.php`**:

- Australian tax brackets and Medicare levy (currently FY2026–27)
- Indian TDS on NRO interest and the India–Australia treaty rate
- Bond and rent-in-advance defaults by state, with links to each tenancy authority
- Starting provider list for the remittance comparator (only used until you save the providers table in admin)
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
| `oz_chat_open` | Ask helper opened | |
| `oz_chat_ask` | question answered | `answered` (false if nothing was found) |
| `oz_chat_click` | link in a reply clicked | `url` |

## Tests

```
node --test ozmoneytalks-tools/tests/calc.test.js
php ozmoneytalks-tools/tests/chat.test.php
```

The first covers the remittance, rent, affordability, savings/tax, break-even and rate-history maths, with expected values worked by hand in the test comments. The second covers the Ask helper's matching (tools, rate and advice detection, your answers, search words and scrubbing before logging) against the real keyword lists in `includes/chat-data.php`.

## Uninstalling

Deleting the plugin (not just deactivating it) removes its settings, provider table, Ask helper answers and question log, and **the subscriber table**. Export the CSV first.
