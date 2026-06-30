# Text the datasheet via Twilio

Lets a visitor on a single product page enter their mobile number and receive
an **SMS with a link to that product's datasheet** — the same documents shown
in the "View / Print Product Information" dropdown. It mirrors the
email-the-datasheet flow, but over text.

## Files

| File | Purpose |
| --- | --- |
| `inc/twilio-datasheet-sms.php` | The whole feature: admin settings page, AJAX endpoint, Twilio sender, and the `abs_datasheet_sms_form()` template helper. |
| `assets/js/datasheet-sms.js` | Front-end behaviour for the form (toggle + AJAX submit). |
| `single-product.php` | Calls `abs_datasheet_sms_form()` below the document dropdown. |

## Install

1. Copy `inc/twilio-datasheet-sms.php` and `assets/js/datasheet-sms.js` into the
   active theme, keeping the `inc/` and `assets/js/` paths.

2. Require the module from the theme's **`functions.php`**:

   ```php
   require_once get_stylesheet_directory() . '/inc/twilio-datasheet-sms.php';
   ```

3. In **wp-admin → Settings → Datasheet SMS**, enter your Twilio:
   - **Account SID** and **Auth Token** (Twilio Console dashboard)
   - **From number** — an SMS-enabled Twilio number in E.164 format, e.g. `+447700900123`
   - **Default country code** — digits only (e.g. `44` for the UK), used when a
     visitor types a local number like `07700 900123`.

That's it. The form appears automatically on product pages that have at least
one datasheet document attached, and only when Twilio is configured.

## How it works

- The browser submits **only the product ID and phone number** (plus a nonce).
- The server looks up the datasheet URL(s) from that product's
  `related_documents_or_other_files` ACF field and builds the message itself, so
  the endpoint can't be abused to text arbitrary links to arbitrary numbers.
- It sends via Twilio's REST API
  (`POST /2010-04-01/Accounts/{SID}/Messages.json`) using `wp_remote_post`.

### Built-in protections

- Nonce verification on every request.
- Phone numbers normalised to E.164 and validated before sending.
- Per-IP rate limit (5 sends/hour) and a hidden honeypot field against bots.
- Twilio errors are logged server-side; visitors only see a friendly message.

## Notes

- Standard Twilio SMS rates apply per message. Long datasheet links may split
  the text into multiple segments (Twilio reassembles them on delivery).
- The Auth Token is stored in the database. For tighter security you can create
  a [Twilio API Key](https://www.twilio.com/docs/iam/api-keys) and use its SID
  + secret in place of the Account SID + Auth Token.
- Sending PDFs as attachments (MMS) instead of links is possible later by adding
  a `MediaUrl` parameter, but link delivery is the most reliable across carriers.
