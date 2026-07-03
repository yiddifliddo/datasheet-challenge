=== Click to Call (Twilio) ===
Contributors: clicktocall
Tags: click to call, twilio, sms, call, callback, sales
Requires at least: 5.6
Tested up to: 6.5
Requires PHP: 7.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A self-contained "Click to Call" button. Visitors enter a US number, confirm by SMS (reply YES), and are connected by phone to your sales team via Twilio.

== Description ==

Add a Click to Call button anywhere with the [click_to_call] shortcode, or switch on
an automatic footer button for every page. When a visitor clicks it they enter a US
phone number and receive a Twilio SMS. As soon as they reply YES, Twilio calls them
and bridges the call to your sales team.

The plugin is 100% standalone: it does not depend on, hook into, or modify your theme
or any other plugin, and it needs no separate server — the Twilio webhooks are handled
by WordPress REST endpoints inside the plugin.

Features:

* [click_to_call] shortcode, placeable anywhere
* Optional floating or centred footer button on all pages
* Full control of text, size, font, weight, colour, background, hover, radius, padding
* Google Fonts support (e.g. Poppins) loaded automatically
* SMS double opt-in with consent checkbox and STOP/HELP handling
* Twilio webhook signature verification, rate limiting, honeypot and US number
  validation
* Single settings screen; your sales number stays server-side

== Installation ==

1. Upload the `click-to-call-twilio` folder to `/wp-content/plugins/`, or install the
   zip via Plugins → Add New → Upload.
2. Activate the plugin.
3. Go to Settings → Click to Call and enter your Twilio Account SID, Auth Token, your
   Twilio phone number and your sales number.
4. In the Twilio Console, set your number's "A message comes in" webhook (HTTP POST) to
   the SMS webhook URL shown on the settings page.
5. Add [click_to_call] to a page, or enable the footer button.

See README.md for a full walkthrough, styling examples and compliance notes.

== Frequently Asked Questions ==

= Do I need a separate server or the Twilio SDK? =
No. Everything runs inside WordPress and talks to Twilio's REST API directly.

= Can I change the button's font to Poppins? =
Yes. Set font="Poppins" (the default) and keep Google Fonts loading enabled, or set any
other Google Font.

= Where does the call go? =
To the single sales number you set in the plugin settings. It is never exposed to the
browser.

== Changelog ==

= 1.0.0 =
* Initial release.
