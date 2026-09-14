# Broadcast Buddy WordPress Plugin

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)
[![WordPress Compatibility](https://img.shields.io/badge/WordPress-5.8%20to%207.1-blue.svg)](https://wordpress.org/plugins/broadcast-buddy/)
[![PHP Compatibility](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net/)

Official WordPress plugin repository for [Broadcast Buddy](https://broadcastbuddy.app).

Connect your WooCommerce store and WordPress website with Broadcast Buddy to send automated WhatsApp order notifications, reduce cart abandonment, and engage site visitors with a lightweight, customizable floating chat widget.

---

## 🌟 Key Features

- **WooCommerce Order Alerts**: Real-time WhatsApp notifications for Processing, Completed, and Cancelled orders with dynamic customer & item tags.
- **Admin Order Notifications**: Instant WhatsApp alerts sent to the store administrator whenever a new order is received.
- **Floating WhatsApp & WebChat Widget**: Customizable greeting card, bot trigger keywords, unminified standalone styling, and mobile-friendly drawer.
- **8-Digit Hotline Phone Pairing**: Link your WhatsApp hotline directly using an 8-digit verification code without QR scanning.
- **Live Test Sandbox**: Send test WhatsApp messages directly from the WordPress admin dashboard.

---

## 📂 Source Code Structure

All code in this repository is 100% human-readable, unminified, vanilla PHP, JavaScript, and standard CSS3:

```text
├── broadcastbuddy.php          # Main plugin file & admin dashboard logic
├── broadcastbuddy-widget.js     # Human-readable vanilla JavaScript widget client
├── broadcastbuddy-widget.css    # Clean CSS3 stylesheet for the chat widget
├── readme.txt                  # Standard WordPress.org plugin directory readme
├── README.md                   # Repository overview & documentation
└── LICENSE                     # MIT Open Source License
```

> **Note**: No compilers, bundlers (Webpack, Vite, Rollup, Babel), minifiers, or obfuscators are used. The files in this repository are the exact raw source code used in production and distributed via the WordPress.org Plugin Directory.

---

## 🚀 Development & Local Testing

1. Clone this repository into your WordPress `wp-content/plugins/` directory:
   ```bash
   cd wp-content/plugins
   git clone https://github.com/Broadcast-Buddy/wordpress-plugin.git broadcastbuddy
   ```
2. Activate the plugin via **WordPress Admin > Plugins**.
3. Enable `WP_DEBUG` in `wp-config.php` to review any runtime warnings or logs during development:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).
