=== Broadcast Buddy ===
Contributors: broadcastbuddy
Tags: whatsapp, woocommerce, notifications, chat, order tracking
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.5
License: MIT
License URI: https://opensource.org/licenses/MIT

Automated WhatsApp order notifications, live connection management, test sandbox, and floating chat widget powered by Broadcast Buddy.

== Description ==

Connect your website and WooCommerce store with Broadcast Buddy to send instant, automated WhatsApp notifications for every order lifecycle event, reduce cart abandonment, and convert store visitors with an interactive floating chat widget.

### Key Features
* **Order Status Alerts**: Automatically notify customers on WhatsApp when their order is Processing, Completed, or Cancelled.
* **Admin Notifications**: Receive immediate WhatsApp alerts for new incoming orders with order totals and item summaries.
* **Floating WhatsApp Widget**: Plug-and-play greeting widget for pages and products with bot trigger keywords.
* **8-Digit Phone Pairing**: Link your WhatsApp hotline directly with an 8-digit phone code without QR scanning.
* **Live Test Sandbox**: Dispatch test messages directly from your WordPress admin dashboard.

== Source Code & Development ==

The Broadcast Buddy WordPress plugin is distributed with full source transparency and human-readable code.

* Official Public Repository: https://github.com/Broadcast-Buddy/wordpress-plugin
* Plugin Source Files: https://github.com/Broadcast-Buddy/wordpress-plugin

### Build & Contribution Instructions
All JavaScript and CSS files included in this plugin are standard, unminified, human-readable vanilla JavaScript (ES5/ES6 compatible) and CSS3 stylesheets.
No compilers (such as Webpack, Babel, Vite, Rollup, or TypeScript) and no minifiers or obfuscators are used to generate the distributed production files for this WordPress plugin.

To inspect, develop, or fork:
1. Clone the public repository: `git clone https://github.com/Broadcast-Buddy/wordpress-plugin.git`
2. Inspect or edit `broadcastbuddy.php`, `broadcastbuddy-widget.js`, or `broadcastbuddy-widget.css` directly.
3. Test on a WordPress environment with `WP_DEBUG` enabled.

== External services ==

This plugin relies on external services provided by Broadcast Buddy to connect with WhatsApp and dispatch order notifications and manage live hotline sessions.

### Broadcast Buddy API (https://broadcastbuddy.app/api/v1)
* **What it is used for**:
  1. **Hotline Connection & Status Checking**: Verifies whether your WhatsApp hotline is connected and online.
  2. **8-Digit Hotline Phone Pairing**: Generates an 8-digit verification code to link your WhatsApp phone without scanning QR codes.
  3. **Order Notifications & Test Sandbox**: Delivers automated WooCommerce customer alerts, admin order notifications, and test messages through your connected WhatsApp hotline.
* **Data transmitted and when**:
  * **When checking status**: Transmits the administrator's configured API Key to verify session status.
  * **When requesting pairing code**: Transmits the administrator's WhatsApp phone number and API Key.
  * **When dispatching order notifications**: Transmits the recipient's phone number and the rendered message text (which may include the customer's name, order ID, order total, and purchased item list as configured in your notification templates) to the Broadcast Buddy API endpoint.
* **Terms of Service and Privacy Policy**:
  * Broadcast Buddy Terms of Service: [https://broadcastbuddy.app/terms](https://broadcastbuddy.app/terms)
  * Broadcast Buddy Privacy Policy: [https://broadcastbuddy.app/privacy](https://broadcastbuddy.app/privacy)

== Installation ==

1. Upload `broadcastbuddy.zip` through the 'Plugins > Add New > Upload Plugin' screen in WordPress.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to the 'Broadcast Buddy' menu in your WordPress Admin sidebar.
4. Paste your Broadcast Buddy API Key (Session ID) and save.

== Frequently Asked Questions ==

= Where do I find my API Key? =
You can find and copy your API Key (Session ID) from your [Broadcast Buddy Dashboard](https://broadcastbuddy.app).

= Does this plugin require an account with Broadcast Buddy? =
Yes, this plugin connects to the Broadcast Buddy API to route and send WhatsApp messages. You can sign up at [broadcastbuddy.app](https://broadcastbuddy.app).

= What shortcodes are available? =
You can use `[broadcast_buddy_whatsapp_button]` or `[broadcastbuddy_chat_button text="Chat with us"]` to embed a WhatsApp contact button anywhere in your posts or pages.

== Screenshots ==

1. Broadcast Buddy WordPress dashboard showing live hotline connection status and 8-digit pairing.
2. Automated order notification templates for Processing, Completed, and Cancelled orders.
3. Live test sandbox to dispatch instant WhatsApp test messages from your dashboard.
4. Floating WhatsApp greeting chat widget rendered on the storefront.

== Changelog ==

= 1.3.5 =
* Resolved WordPress.org Plugin Directory review feedback regarding script/style enqueueing and source code disclosure.
* Extracted widget CSS into unminified `broadcastbuddy-widget.css` enqueued via standard `wp_enqueue_style`.
* Enqueued admin mode selector stylesheet via `wp_enqueue_style` and `wp_add_inline_style`, removing raw `<style>` tag in settings page.
* Documented bundled uncompiled source code in readme.txt per WordPress.org Guideline #4.

= 1.3.4 =
* Enhanced WooCommerce order status hooks and live test sandbox.
* Added support for bot flow synchronization.

= 1.3.3 =
* Enqueued all admin and frontend scripts and styles using standard WordPress `wp_enqueue_script` and `wp_enqueue_style` APIs.
* Bundled widget script locally to avoid external offloading.
* Renamed and standardized all shortcodes, options, settings groups, and AJAX actions with compliant `broadcast_buddy_` prefix.
* Added comprehensive External Services, Terms of Service, and Privacy Policy disclosures to readme.txt.

= 1.3.2 =
* Renamed plugin to Broadcast Buddy.
* Updated Tested up to: 7.1 WordPress compatibility.
* Added support for 8-digit phone pairing codes.
* Enhanced setting sanitization and security nonce verification.

= 1.0.0 =
* Initial release.
