=== Coin680 Shield -- Anti-Bot & Comment Spam Protection ===
Contributors: coin680
Tags: comments, spam, security, anti-spam, brute force
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Blocks comment spam bots and hardens your site against brute-force logins and basic bot traffic. Unlock every premium feature free with one code.

== Description ==

Coin680 Shield protects your comment forms and login page from automated bots without adding friction for real visitors.

**Free, always on:**

* Invisible honeypot field on the comment form -- bots that auto-fill every input get caught, real visitors never see it.
* Signed time-trap -- rejects comments submitted faster than a human could plausibly type, using an HMAC signature so a bot can't just forge an old timestamp.
* Independent keyword/link blocklist for comments, fully configurable, with a sensible default list included.
* Blocks XML-RPC (a common WordPress brute-force target).
* Blocks known aggressive bot user-agents, with an editable list.
* A simple dashboard showing how many bots were blocked and why.

**Premium, unlocked free with a code:**

* Per-IP comment rate limiting.
* Login brute-force lockout after a configurable number of failed attempts.
* A lightweight request firewall blocking common SQL-injection/XSS-style patterns in the URL and query string.
* Optional simple math CAPTCHA on the comment form.

To unlock every premium feature at no cost, go to **Coin680 Shield** in your wp-admin menu and enter the code `coin680` in the License field. No account, no payment, no expiry -- it's a promotional code from [Coin680](https://coin680.com/), a Bitcoin education and crypto news site.

This plugin makes no external network requests and does not send any data off your site. The license check and all bot-detection logic run entirely locally.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/coin680-shield` directory, or install it directly through the WordPress plugins screen.
2. Activate the plugin through the "Plugins" screen in WordPress.
3. Go to **Coin680 Shield** in your admin menu to review the default settings and, optionally, enter the free unlock code.

== Frequently Asked Questions ==

= Do I have to pay for anything? =

No. Every feature, including the ones labeled "premium," is free. The premium tier is simply unlocked with a code instead of being on by default, as a way to introduce visitors to Coin680.

= Does this plugin send my data anywhere? =

No. There are no external API calls. All checks (honeypot, time-trap, keyword filtering, rate limiting, login lockout, firewall) run entirely on your own server.

= Will the honeypot or time-trap ever block a real visitor? =

The honeypot field is hidden from sighted users and screen readers alike, and the time-trap threshold defaults to 3 seconds, well below how long it takes a human to read and fill out a comment form. Both are adjustable in settings.

= Can I use my own blocklist words? =

Yes, the keyword/link blocklist is fully editable from the settings screen and works independently of WordPress's built-in Disallowed Comment Keys list.

== Screenshots ==

1. The Coin680 Shield settings screen, showing license status and blocked-attempt stats.

== Changelog ==

= 1.0.0 =
* Initial release: honeypot, signed time-trap, keyword/link blocklist, bad-bot user-agent blocking, and XML-RPC blocking (free); rate limiting, login brute-force lockout, request firewall, and math CAPTCHA (unlocked with a free code).

== Upgrade Notice ==

= 1.0.0 =
Initial release.
