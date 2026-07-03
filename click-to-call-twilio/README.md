# Click to Call (Twilio)

A **100% self-contained** WordPress plugin that adds a *Click to Call* button to your
site. A visitor enters a US phone number, gets a Twilio SMS asking them to confirm,
and the moment they reply **YES** their phone rings and connects them to your sales
team.

It does not depend on, hook into, or change your theme or any other plugin. Drop it
in, add your Twilio details, and place the button anywhere with a shortcode — or turn
on the automatic footer button for every page.

---

## How it works

```
 Visitor clicks "Click to Call"
          │
          ▼
 Pop-up asks for a US phone number  ──►  POST /wp-json/click-to-call/v1/request
          │                                     │  validates + rate-limits
          │                                     ▼
          │                              Twilio sends confirmation SMS
          ▼
 Visitor replies  YES  ────────────►  Twilio → POST /wp-json/click-to-call/v1/sms
                                              │  (signature verified)
                                              ▼
                                       Plugin asks Twilio to CALL the visitor
                                              │
                    Visitor answers ──►  Twilio → POST /wp-json/click-to-call/v1/voice
                                              │  returns TwiML
                                              ▼
                                    "Please hold…"  <Dial> your sales number
                                              │
                                              ▼
                                 Visitor ↔ Sales team connected
```

Nothing but the plugin is involved — the webhooks are standard WordPress REST routes,
so there is no separate server to host.

---

## Requirements

* WordPress 5.6+ and PHP 7.2+
* A Twilio account with **one phone number** that has **SMS and Voice** enabled
* Your **sales team phone number** (where confirmed calls are sent)
* Your site must be reachable over **HTTPS** from the public internet (Twilio has to
  reach the webhook)

No Composer, no Twilio SDK, no build step — the plugin talks to Twilio's REST API
directly using WordPress's built-in HTTP functions.

---

## Install

1. Zip the `click-to-call-twilio` folder (the folder that contains
   `click-to-call-twilio.php`).
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the zip, install and
   **Activate**.
3. Go to **Settings → Click to Call**.

*(Or copy the `click-to-call-twilio` folder into `wp-content/plugins/` and activate.)*

---

## Twilio setup (one time)

1. **Get your credentials.** In the [Twilio Console](https://console.twilio.com/)
   dashboard, copy your **Account SID** and **Auth Token**.
2. **Check your number.** Under **Phone Numbers → Manage → Active numbers**, make sure
   your number has **Voice** and **Messaging** capabilities. Note it in E.164 format,
   e.g. `+14155552671`.
3. **Set the SMS webhook.** Open your number and scroll to **Messaging → "A message
   comes in"**. Set it to **Webhook**, method **HTTP POST**, and paste the SMS webhook
   URL shown on the plugin settings page:

   ```
   https://YOUR-SITE.com/wp-json/click-to-call/v1/sms
   ```

   Save. *(You do not need to configure a Voice webhook — the plugin creates the call
   and supplies the voice instructions automatically.)*

That's it on the Twilio side.

---

## Plugin settings

**Settings → Click to Call**

| Field | What to enter |
|---|---|
| Account SID | From your Twilio dashboard |
| Auth Token | From your Twilio dashboard |
| Your Twilio phone number | The number that texts and calls, e.g. `+14155552671` |
| Sales team phone number | Where confirmed calls connect, e.g. `+13105550101` |
| Verify Twilio signatures | Leave **on** (recommended) |
| Confirmation SMS | The text people receive. Keep the word **YES**. `{site}` = your site name |
| Spoken greeting | Read aloud right before connecting the caller |
| Max requests per visitor / hour | Anti-abuse limit (default 5) |

The page also shows the exact **SMS webhook URL** to paste into Twilio and a
**Connected / Not connected** status banner.

---

## Placing the button

### Shortcode (anywhere)

```
[click_to_call]
```

Works in pages, posts, blocks, widgets, and template files:

```php
<?php echo do_shortcode('[click_to_call]'); ?>
```

### Automatic footer button (all pages)

Turn on **"Show a button in the footer of every page"** in settings. Choose:

* **Floating button** — a rounded button pinned to the bottom-left or bottom-right
  corner, on every page.
* **Centred button** — a button centred at the very bottom of each page.

---

## Full styling control

Every visual property is controllable — per button via shortcode attributes, or
globally via the settings defaults.

| Attribute | Example | Meaning |
|---|---|---|
| `text` | `text="Speak to Sales"` | Button label |
| `bg` | `bg="#e63946"` | Background colour (hex, rgb/rgba, hsl, or name) |
| `color` | `color="#ffffff"` | Text colour |
| `hover_bg` | `hover_bg="#b5171f"` | Background on hover |
| `hover_color` | `hover_color="#fff"` | Text colour on hover |
| `font` | `font="Poppins"` | Font family |
| `gfont` | `gfont="1"` | Load that font from Google Fonts |
| `size` | `size="18px"` | Font size |
| `weight` | `weight="600"` | Font weight |
| `radius` | `radius="30px"` | Corner radius |
| `pad_y` | `pad_y="14px"` | Vertical padding |
| `pad_x` | `pad_x="30px"` | Horizontal padding |
| `width` | `width="full"` | `full` = 100% wide, `auto` = fit content |
| `align` | `align="center"` | `left` / `center` / `right` |
| `icon` | `icon="0"` | `1` show the phone icon, `0` hide it |
| `class` | `class="my-btn"` | Add your own CSS class |

### Examples

A Poppins pill button in your brand red:

```
[click_to_call text="Call Us Now" font="Poppins" gfont="1" bg="#e63946" color="#ffffff" hover_bg="#b5171f" size="18px" weight="600" radius="40px" pad_x="34px"]
```

A large, full-width call-to-action:

```
[click_to_call text="📞 Talk to Sales" width="full" size="20px" pad_y="18px" radius="12px" align="center"]
```

A minimal text-coloured button with no icon:

```
[click_to_call text="Request a call" bg="transparent" color="#0b5cff" icon="0"]
```

**About Poppins / Google Fonts:** set the font to `Poppins` (default) and keep
*"Load this font from Google Fonts"* enabled — the plugin loads Poppins itself, so it
renders correctly even if your theme doesn't include it. Any Google Font works
(Roboto, Montserrat, Inter, …). Adjust weights with the **Google Font weights** field
(e.g. `400;600;700`).

The button and pop-up are namespaced under `.ctc-*` classes and reset the properties
that matter, so your theme won't distort them and they won't affect your theme.

---

## Testing

1. Add `[click_to_call]` to a test page (or enable the footer button) and open the
   page in your browser.
2. Click the button, enter **your own** US mobile, tick the consent box, submit.
3. You'll receive the confirmation text. Reply **YES**.
4. Your phone rings; answer it and you'll be connected to the sales number.

If something fails, check **Twilio Console → Monitor → Logs** (Messaging and Voice/
Calls) and your server's PHP error log — send failures are logged there.

---

## Compliance & privacy

* **Double opt-in.** We only call after the visitor both requests it on the site *and*
  replies YES by text.
* **Consent checkbox.** The pop-up requires an explicit tick before sending anything.
  Edit the wording in settings.
* **STOP / HELP.** Replies of `STOP` (and `UNSUBSCRIBE`, `CANCEL`, `END`, `QUIT`) get
  an opt-out reply and clear any pending request; `HELP`/`INFO` get a help reply.
* Keep your SMS wording accurate for TCPA/CTIA. The default includes *"Msg & data
  rates may apply"* and how to cancel.

## Security

* Twilio webhooks are verified with the **X-Twilio-Signature** header (HMAC-SHA1 with
  your auth token). Unsigned/forged requests are rejected. You can disable this only if
  a proxy rewrites your URLs.
* The public *request* endpoint is protected by a per-visitor hourly **rate limit**, a
  per-number **cooldown**, strict **US number validation**, a **honeypot** field, and a
  required **consent** flag.
* Your **sales number is never exposed** to the browser — it lives only on the server
  and is dialled by Twilio.

## Costs

Twilio charges its normal per-SMS and per-minute rates for the confirmation text and
the connected call. This plugin adds nothing on top.

## Uninstalling

Deleting the plugin removes its single settings option and its transients — it leaves
no trace.

---

## File overview

```
click-to-call-twilio/
├── click-to-call-twilio.php        Main plugin file (bootstrap, activation)
├── uninstall.php                   Clean removal
├── includes/
│   ├── class-ctc-settings.php      Settings storage + admin screen
│   ├── class-ctc-phone.php         US (NANP) phone validation
│   ├── class-ctc-twilio.php        Twilio REST client + signature check
│   ├── class-ctc-rest.php          request / sms / voice endpoints
│   └── class-ctc-shortcode.php     Shortcode, footer button, styling
└── assets/
    ├── ctc.css                     Self-contained styles
    └── ctc.js                      Pop-up + AJAX (vanilla JS)
```
