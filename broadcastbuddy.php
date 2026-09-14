<?php
/**
 * Plugin Name: Broadcast Buddy
 * Description: Automated WhatsApp order notifications, live connection management, test sandbox, and interactive/hybrid floating chat widget with bot flow automation powered by Broadcast Buddy.
 * Version: 1.3.5
 * Author: Broadcast Buddy Team
 * Author URI: https://broadcastbuddy.app
 * Support: support@broadcastbuddy.app
 * Source Code: https://github.com/Broadcast-Buddy/wordpress-plugin
 * Text Domain: broadcastbuddy
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.2
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class BroadcastBuddy {
    const API_URL = 'https://broadcastbuddy.app/api/v1';
    const SUPPORT_EMAIL = 'support@broadcastbuddy.app';
    private $api_key;

    public function __construct() {
        $this->api_key = $this->get_opt('broadcast_buddy_api_key', 'bb_api_key', '');

        // Admin Menus, Scripts & Settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'register_settings'));

        // AJAX Handlers for Testing, Live Status & Flow Sync
        add_action('wp_ajax_broadcast_buddy_check_status', array($this, 'ajax_check_status'));
        add_action('wp_ajax_broadcast_buddy_send_test', array($this, 'ajax_send_test'));
        add_action('wp_ajax_broadcast_buddy_request_pairing', array($this, 'ajax_request_pairing'));
        add_action('wp_ajax_broadcast_buddy_fetch_flows', array($this, 'ajax_fetch_flows'));

        // Frontend Floating Widget Enqueue
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_widget'));

        // Shortcodes for Posts & Blogs (strictly prefixed)
        add_shortcode('broadcastbuddy_chat_button', array($this, 'shortcode_chat_button'));
        add_shortcode('broadcast_buddy_whatsapp_button', array($this, 'shortcode_chat_button'));
        add_shortcode('broadcast_buddy_button', array($this, 'shortcode_chat_button'));

        // WooCommerce Order Hooks
        add_action('woocommerce_order_status_completed', array($this, 'notify_order_completed'), 10, 1);
        add_action('woocommerce_order_status_processing', array($this, 'notify_order_processing'), 10, 1);
        add_action('woocommerce_order_status_cancelled', array($this, 'notify_order_cancelled'), 10, 1);
        add_action('woocommerce_new_order', array($this, 'notify_admin_new_order'), 10, 1);
    }

    /**
     * Helper to read option with backwards-compatible fallback.
     */
    private function get_opt($primary_key, $fallback_key, $default = '') {
        $val = get_option($primary_key, null);
        if ($val !== null && $val !== false) {
            return $val;
        }
        $fallback_val = get_option($fallback_key, null);
        if ($fallback_val !== null && $fallback_val !== false) {
            return $fallback_val;
        }
        return $default;
    }

    public function add_admin_menu() {
        add_menu_page(
            'Broadcast Buddy',
            'Broadcast Buddy',
            'manage_options',
            'broadcast-buddy',
            array($this, 'settings_page'),
            'dashicons-format-chat',
            56
        );
    }

    /**
     * Enqueue Admin Assets properly using standard WordPress APIs
     */
    public function enqueue_admin_assets($hook) {
        // Enqueue admin menu icon styling across admin
        wp_register_style('broadcast-buddy-admin-menu', false, array(), '1.3.5');
        wp_enqueue_style('broadcast-buddy-admin-menu');
        $menu_css = '
            #toplevel_page_broadcast-buddy .wp-menu-image:before {
                color: #25D366 !important;
            }
            #toplevel_page_broadcast-buddy .wp-menu-image img {
                max-width: 20px !important;
                max-height: 20px !important;
                padding-top: 7px !important;
                object-fit: contain !important;
            }
        ';
        wp_add_inline_style('broadcast-buddy-admin-menu', $menu_css);

        // Only enqueue admin dashboard scripts and styles on the Broadcast Buddy settings page
        if ($hook !== 'toplevel_page_broadcast-buddy') {
            return;
        }

        // Register and enqueue admin dashboard styles via standard WordPress API
        wp_register_style('broadcast-buddy-admin', false, array(), '1.3.5');
        wp_enqueue_style('broadcast-buddy-admin');
        $admin_css = '
            .bb-mode-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 6px; }
            .bb-mode-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 14px; cursor: pointer; transition: all 0.2s ease; background: #fff; position: relative; }
            .bb-mode-card:hover { border-color: #cbd5e1; background: #f8fafc; }
            .bb-mode-card.active-mode { border-color: #10b981; background: #f0fdf4; box-shadow: 0 0 0 1px #10b981; }
            .bb-mode-card input[type="radio"] { position: absolute; opacity: 0; }
            .bb-mode-title { font-weight: 700; font-size: 13px; color: #0f172a; margin-bottom: 4px; display: flex; align-items: center; justify-content: space-between; }
            .bb-mode-desc { font-size: 11px; color: #64748b; line-height: 1.35; }
        ';
        wp_add_inline_style('broadcast-buddy-admin', $admin_css);

        wp_register_script('broadcast-buddy-admin', false, array('jquery'), '1.3.5', true);
        wp_enqueue_script('broadcast-buddy-admin');

        $api_key = $this->get_opt('broadcast_buddy_api_key', 'bb_api_key', '');
        $admin_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('broadcast_buddy_admin_action'),
            'api_key'  => $api_key,
        );
        wp_localize_script('broadcast-buddy-admin', 'BroadcastBuddyAdmin', $admin_data);

        $admin_js = '
        jQuery(document).ready(function($) {
            var bbAdminNonce = BroadcastBuddyAdmin.nonce;
            var ajaxurl = BroadcastBuddyAdmin.ajax_url;

            // Live Preview Updates
            function updatePreview() {
                var mode = $("input[name=\"broadcast_buddy_widget_mode\"]:checked").val() || "whatsapp";
                var title = $("#broadcast_buddy_widget_title").val() || "Hi there!";
                var subtitle = $("#broadcast_buddy_widget_subtitle").val() || "Have a question? Chat with us.";
                var btnText = $("#broadcast_buddy_widget_btn_text").val() || "Start chat →";
                var botName = $("#broadcast_buddy_widget_bot_name").val() || "Assistant";
                var color = $("#broadcast_buddy_widget_color").val() || "#25D366";
                var showWa = $("#broadcast_buddy_widget_show_wa_btn").is(":checked");

                $("#bb-preview-title").text(title);
                $("#bb-preview-subtitle").text(subtitle);
                $("#bb-preview-btn").text(btnText);
                $("#bb-preview-bot-name").text(botName);
                $("#bb-preview-icon, #bb-preview-circle, #bb-preview-bot-avatar").css("background-color", color);
                $("#bb-preview-btn").css("color", color);

                if (mode === "webchat" || mode === "hybrid") {
                    $("#bb-preview-bot-badge").show();
                    $("#bb-preview-interactive-options").show();
                    if (mode === "hybrid" && showWa) {
                        $("#bb-preview-wa-handoff").show();
                    } else {
                        $("#bb-preview-wa-handoff").hide();
                    }
                } else {
                    $("#bb-preview-bot-badge").hide();
                    $("#bb-preview-interactive-options").hide();
                    $("#bb-preview-wa-handoff").hide();
                }
            }

            $("#broadcast_buddy_widget_title, #broadcast_buddy_widget_subtitle, #broadcast_buddy_widget_btn_text, #broadcast_buddy_widget_bot_name").on("input", updatePreview);
            $("#broadcast_buddy_widget_color").on("input", updatePreview);
            $("#broadcast_buddy_widget_show_wa_btn").on("change", updatePreview);

            // Mode Selector Cards click
            $(".bb-mode-card").on("click", function() {
                var mode = $(this).data("mode");
                $("input[name=\"broadcast_buddy_widget_mode\"][value=\"" + mode + "\"]").prop("checked", true).trigger("change");
            });

            $("input[name=\"broadcast_buddy_widget_mode\"]").on("change", function() {
                var selected = $(this).val();
                $(".bb-mode-card").removeClass("active-mode");
                $(".bb-mode-card[data-mode=\"" + selected + "\"]").addClass("active-mode");

                if (selected === "webchat" || selected === "hybrid") {
                    $("#bb-bot-flow-section").slideDown();
                } else {
                    $("#bb-bot-flow-section").slideUp();
                }
                updatePreview();
            });

            // Fetch Bot Flows via AJAX
            function fetchFlows(isManualClick) {
                var apiKey = (window.BroadcastBuddyAdmin && BroadcastBuddyAdmin.api_key) || $("#broadcast_buddy_api_key").val() || "";
                var $btn = $("#bb-refresh-flows-btn");
                var $status = $("#bb-flow-status-msg");
                var $select = $("#broadcast_buddy_widget_flow_id");

                if (!apiKey) {
                    $status.html("<span style=\"color:#ef4444;font-weight:600;\">⚠️ No API Key found. Please save your API Key in the Connection tab first.</span>");
                    return;
                }

                $btn.prop("disabled", true).html("⏳ Syncing...");
                $status.html("<span style=\"color:#64748b;\">⏳ Fetching bot flows from account...</span>");

                $.post(ajaxurl, {
                    action: "broadcast_buddy_fetch_flows",
                    nonce: bbAdminNonce,
                    api_key: apiKey
                }, function(res) {
                    $btn.prop("disabled", false);
                    if (res.success && res.data.flows) {
                        var flows = res.data.flows;
                        var currentVal = $select.val() || $select.attr("data-current-val") || "";
                        $select.find("option:not([value=\"\"])").remove();

                        if (flows.length === 0) {
                            $status.html("<span style=\"color:#f59e0b;font-weight:600;\">ℹ️ No bot flows created yet in your dashboard. <a href=\"https://broadcastbuddy.app/bot-designer\" target=\"_blank\" style=\"color:#10b981;text-decoration:underline;\">Create one here ↗</a></span>");
                            $btn.html("🔄 Refresh Flows");
                            return;
                        }

                        $.each(flows, function(i, f) {
                            var isSelected = (String(f.id) === String(currentVal) || String(f.flowKey) === String(currentVal));
                            var label = f.name + (f.description ? " — " + f.description : "");
                            var opt = $("<option></option>").val(f.id).text(label);
                            if (isSelected) opt.prop("selected", true);
                            $select.append(opt);
                        });

                        $select.css({ "border-color": "#10b981", "box-shadow": "0 0 0 2px rgba(16, 185, 129, 0.25)" });
                        setTimeout(function() {
                            $select.css({ "border-color": "", "box-shadow": "" });
                        }, 2000);

                        $status.html("<span style=\"color:#059669;font-weight:700;\">✅ " + flows.length + " flow" + (flows.length === 1 ? "" : "s") + " synced successfully!</span>");
                        $btn.html("✅ Synced");
                        setTimeout(function() {
                            $btn.html("🔄 Refresh Flows");
                        }, 2500);
                    } else {
                        var errMsg = res.data ? (res.data.message || res.data.error || "Unknown response") : "Failed to fetch flows";
                        $status.html("<span style=\"color:#ef4444;font-weight:600;\">❌ " + errMsg + "</span>");
                        $btn.html("🔄 Retry Sync");
                    }
                }).fail(function(xhr) {
                    $btn.prop("disabled", false).html("🔄 Retry Sync");
                    $status.html("<span style=\"color:#ef4444;font-weight:600;\">❌ Server communication error (" + (xhr.status || 500) + ")</span>");
                });
            }

            $("#bb-refresh-flows-btn").on("click", function() {
                fetchFlows(true);
            });

            if ($("#broadcast_buddy_widget_flow_id").length) {
                fetchFlows(false);
            }

            // Check Status Handler
            function checkStatus() {
                var apiKey = $("#broadcast_buddy_api_key").val();
                if (!apiKey) {
                    $("#bb-status-dot").css("background", "#ef4444");
                    $("#bb-status-text").text("No API Key configured");
                    $("#bb-status-detail").text("Please enter your Broadcast Buddy API Key above and save.");
                    return;
                }

                $("#bb-check-status-btn").prop("disabled", true).text("Checking...");
                $.post(ajaxurl, { action: "broadcast_buddy_check_status", nonce: bbAdminNonce, api_key: apiKey }, function(res) {
                    $("#bb-check-status-btn").prop("disabled", false).text("🔄 Check Status Now");
                    if (res.success && res.data.connected) {
                        $("#bb-status-dot").css("background", "#10b981");
                        $("#bb-status-text").css("color", "#065f46").text("Connected & Ready (" + (res.data.phone || "Online") + ")");
                        $("#bb-status-detail").text("Hotline is active and ready to dispatch WhatsApp notifications.");
                        $("#bb-pairing-box").hide();
                    } else {
                        $("#bb-status-dot").css("background", "#f59e0b");
                        $("#bb-status-text").css("color", "#92400e").text("Disconnected / Standby");
                        $("#bb-status-detail").text(res.data ? (res.data.message || "WhatsApp session is not currently active.") : "Disconnected");
                        $("#bb-pairing-box").slideDown();
                    }
                }).fail(function() {
                    $("#bb-check-status-btn").prop("disabled", false).text("🔄 Check Status Now");
                    $("#bb-status-dot").css("background", "#ef4444");
                    $("#bb-status-text").text("Error connecting to API");
                });
            }

            $("#bb-check-status-btn").on("click", checkStatus);
            if ($("#bb-status-box").length) {
                checkStatus();
            }

            // Request 8-Digit Pairing Code Handler
            $("#bb-request-code-btn").on("click", function() {
                var apiKey = $("#broadcast_buddy_api_key").val();
                var phone = $("#bb-pairing-phone").val();
                if (!phone) {
                    alert("Please enter your WhatsApp phone number.");
                    return;
                }
                var btn = $(this).prop("disabled", true).text("Requesting...");
                $.post(ajaxurl, { action: "broadcast_buddy_request_pairing", nonce: bbAdminNonce, api_key: apiKey, phone: phone }, function(res) {
                    btn.prop("disabled", false).text("Get 8-Digit Code");
                    if (res.success && res.data.pairingCode) {
                        $("#bb-code-display").text(res.data.pairingCode).slideDown();
                    } else {
                        alert("Error: " + (res.data ? res.data.message : "Could not request code"));
                    }
                }).fail(function() {
                    btn.prop("disabled", false).text("Get 8-Digit Code");
                    alert("Connection failed.");
                });
            });

            // Send Test Message Handler
            $("#bb-send-test-btn").on("click", function() {
                var apiKey = $("#broadcast_buddy_api_key").val();
                var recipient = $("#bb-test-recipient").val();
                var message = $("#bb-test-message").val();

                if (!recipient || !message) {
                    alert("Please enter recipient phone number and message.");
                    return;
                }

                var btn = $(this).prop("disabled", true).text("Sending...");
                var resBox = $("#bb-test-result").slideUp();

                $.post(ajaxurl, {
                    action: "broadcast_buddy_send_test",
                    nonce: bbAdminNonce,
                    api_key: apiKey,
                    recipient: recipient,
                    message: message
                }, function(res) {
                    btn.prop("disabled", false).text("📤 Send Live Test WhatsApp");
                    resBox.slideDown();
                    if (res.success) {
                        resBox.css({ background: "#f0fdf4", color: "#166534", border: "1px solid #bbf7d0" })
                              .text("✅ Message Dispatched Successfully!\n\nResponse:\n" + JSON.stringify(res.data, null, 2));
                    } else {
                        resBox.css({ background: "#fef2f2", color: "#991b1b", border: "1px solid #fecaca" })
                              .text("❌ Delivery Failed:\n\n" + (res.data ? (res.data.message || JSON.stringify(res.data, null, 2)) : "Unknown error"));
                    }
                }).fail(function() {
                    btn.prop("disabled", false).text("📤 Send Live Test WhatsApp");
                    resBox.css({ background: "#fef2f2", color: "#991b1b", border: "1px solid #fecaca" })
                          .text("❌ Request Failed. Please verify your server connection.").slideDown();
                });
            });

            updatePreview();
        });
        ';
        wp_add_inline_script('broadcast-buddy-admin', $admin_js);
    }

    public function register_settings() {
        // Tab 1: Connection Group
        register_setting('broadcast_buddy_connection_group', 'broadcast_buddy_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => ''
        ));
        register_setting('broadcast_buddy_connection_group', 'broadcast_buddy_admin_phone', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => ''
        ));

        // Tab 2: Widget Group
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_enabled', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_mode', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'whatsapp'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_flow_id', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => ''
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_bot_name', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Assistant'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_bot_avatar', array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => ''
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_show_wa_btn', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_display_on', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'all'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_phone', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => ''
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_title', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Hi there!'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_subtitle', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => 'Have a question? Chat with our team on WhatsApp.'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_btn_text', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Start chat →'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_trigger_text', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'Hello! I have an inquiry.'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_position', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'bottom-right'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_color', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_hex_color',
            'default'           => '#25D366'
        ));
        register_setting('broadcast_buddy_widget_group', 'broadcast_buddy_widget_auto_open', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '3500'
        ));

        // Tab 3: WooCommerce Group
        register_setting('broadcast_buddy_woocommerce_group', 'broadcast_buddy_notify_admin_new', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1'
        ));
        register_setting('broadcast_buddy_woocommerce_group', 'broadcast_buddy_template_admin_new', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => ''
        ));
        register_setting('broadcast_buddy_woocommerce_group', 'broadcast_buddy_notify_customer_processing', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1'
        ));
        register_setting('broadcast_buddy_woocommerce_group', 'broadcast_buddy_template_customer_processing', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => ''
        ));
        register_setting('broadcast_buddy_woocommerce_group', 'broadcast_buddy_notify_customer_completed', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '1'
        ));
        register_setting('broadcast_buddy_woocommerce_group', 'broadcast_buddy_template_customer_completed', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => ''
        ));
        register_setting('broadcast_buddy_woocommerce_group', 'broadcast_buddy_notify_customer_cancelled', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '0'
        ));
        register_setting('broadcast_buddy_woocommerce_group', 'broadcast_buddy_template_customer_cancelled', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => ''
        ));
    }

    /* ─── Admin Dashboard Page ─── */
    public function settings_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation parameter for UI view only, no state-changing action.
        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'connection';
        $allowed_tabs = array('connection', 'widget', 'woocommerce', 'testing');
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = 'connection';
        }
        $api_key = $this->get_opt('broadcast_buddy_api_key', 'bb_api_key', '');
        $admin_phone = $this->get_opt('broadcast_buddy_admin_phone', 'bb_admin_phone', '');
        
        // Widget Defaults with strict fallback
        $raw_widget_enabled = $this->get_opt('broadcast_buddy_widget_enabled', 'bb_widget_enabled', '1');
        $widget_enabled = ($raw_widget_enabled !== '0' && $raw_widget_enabled !== 'off');

        $widget_mode = $this->get_opt('broadcast_buddy_widget_mode', 'bb_widget_mode', 'whatsapp');
        if (!in_array($widget_mode, array('whatsapp', 'webchat', 'hybrid'), true)) {
            $widget_mode = 'whatsapp';
        }

        $widget_flow_id = $this->get_opt('broadcast_buddy_widget_flow_id', 'bb_widget_flow_id', '');
        $widget_bot_name = $this->get_opt('broadcast_buddy_widget_bot_name', 'bb_widget_bot_name', 'Assistant');
        $widget_bot_avatar = $this->get_opt('broadcast_buddy_widget_bot_avatar', 'bb_widget_bot_avatar', '');
        
        $raw_show_wa_btn = $this->get_opt('broadcast_buddy_widget_show_wa_btn', 'bb_widget_show_wa_btn', '1');
        $widget_show_wa_btn = ($raw_show_wa_btn !== '0' && $raw_show_wa_btn !== 'off');

        $widget_display_on = $this->get_opt('broadcast_buddy_widget_display_on', 'bb_widget_display_on', 'all');
        if (empty($widget_display_on)) $widget_display_on = 'all';

        $widget_phone = $this->get_opt('broadcast_buddy_widget_phone', 'bb_widget_phone', $admin_phone);
        if (empty($widget_phone)) $widget_phone = $admin_phone;

        $widget_title = $this->get_opt('broadcast_buddy_widget_title', 'bb_widget_title', 'Hi there!');
        if (trim($widget_title) === '') $widget_title = 'Hi there!';

        $widget_subtitle = $this->get_opt('broadcast_buddy_widget_subtitle', 'bb_widget_subtitle', 'Have a question? Chat with our team on WhatsApp.');
        if (trim($widget_subtitle) === '') $widget_subtitle = 'Have a question? Chat with our team on WhatsApp.';

        $widget_btn_text = $this->get_opt('broadcast_buddy_widget_btn_text', 'bb_widget_btn_text', 'Start chat →');
        if (trim($widget_btn_text) === '') $widget_btn_text = 'Start chat →';

        $widget_trigger_text = $this->get_opt('broadcast_buddy_widget_trigger_text', 'bb_widget_trigger_text', 'Hello! I have an inquiry.');
        if (trim($widget_trigger_text) === '') $widget_trigger_text = 'Hello! I have an inquiry.';

        $widget_position = $this->get_opt('broadcast_buddy_widget_position', 'bb_widget_position', 'bottom-right');
        if (empty($widget_position)) $widget_position = 'bottom-right';

        $widget_color = $this->get_opt('broadcast_buddy_widget_color', 'bb_widget_color', '#25D366');
        if (empty($widget_color)) $widget_color = '#25D366';

        $widget_auto_open = $this->get_opt('broadcast_buddy_widget_auto_open', 'bb_widget_auto_open', '3500');
        if ($widget_auto_open === '') $widget_auto_open = '3500';

        // WooCommerce Notification Defaults with strict fallback
        $raw_notify_admin_new = $this->get_opt('broadcast_buddy_notify_admin_new', 'bb_notify_admin_new', '1');
        $notify_admin_new = ($raw_notify_admin_new !== '0' && $raw_notify_admin_new !== 'off');

        $template_admin_new = $this->get_opt('broadcast_buddy_template_admin_new', 'bb_template_admin_new', '');
        if (trim($template_admin_new) === '') {
            $template_admin_new = "🚨 *New Order Alert!*\nOrder: #{order_id}\nCustomer: {customer_name}\nTotal: {order_total}\nItems: {items_list}";
        }
        
        $raw_notify_customer_processing = $this->get_opt('broadcast_buddy_notify_customer_processing', 'bb_notify_customer_processing', '1');
        $notify_customer_processing = ($raw_notify_customer_processing !== '0' && $raw_notify_customer_processing !== 'off');

        $template_customer_processing = $this->get_opt('broadcast_buddy_template_customer_processing', 'bb_template_customer_processing', '');
        if (trim($template_customer_processing) === '') {
            $template_customer_processing = "Hi *{first_name}*, thank you for your order *#{order_id}*! Total: {order_total}. We are currently processing it and will update you once shipped.";
        }

        $raw_notify_customer_completed = $this->get_opt('broadcast_buddy_notify_customer_completed', 'bb_notify_customer_completed', '1');
        $notify_customer_completed = ($raw_notify_customer_completed !== '0' && $raw_notify_customer_completed !== 'off');

        $template_customer_completed = $this->get_opt('broadcast_buddy_template_customer_completed', 'bb_template_customer_completed', '');
        if (trim($template_customer_completed) === '') {
            $template_customer_completed = "Hi *{first_name}*, exciting news! Your order *#{order_id}* is completed and on its way to you. Thank you for shopping with us!";
        }

        $raw_notify_customer_cancelled = $this->get_opt('broadcast_buddy_notify_customer_cancelled', 'bb_notify_customer_cancelled', '0');
        $notify_customer_cancelled = ($raw_notify_customer_cancelled === '1');

        $template_customer_cancelled = $this->get_opt('broadcast_buddy_template_customer_cancelled', 'bb_template_customer_cancelled', '');
        if (trim($template_customer_cancelled) === '') {
            $template_customer_cancelled = "Hi *{first_name}*, your order *#{order_id}* has been cancelled. If you have questions, reply to this message.";
        }
        ?>
        <div class="wrap bb-admin-wrapper" style="max-width: 1100px; margin: 20px 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
            <!-- Brand Header -->
            <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #fff; padding: 22px 28px; border-radius: 16px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 10px 25px rgba(0,0,0,0.1); margin-bottom: 24px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: #10b981; color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(16,185,129,0.3); flex-shrink: 0;">
                        <svg viewBox="0 0 32 32" width="24" height="24" fill="currentColor"><path d="M16 2C8.28 2 2 8.28 2 16c0 2.7.76 5.23 2.08 7.39L2.5 29.5l6.32-1.54C10.9 29.18 13.37 30 16 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm8.17 19.86c-.34.96-1.7 1.83-2.77 2.06-.73.16-1.69.29-4.89-1.04-4.1-1.7-6.73-5.88-6.94-6.15-.2-.27-1.67-2.22-1.67-4.24 0-2.02 1.05-3.01 1.43-3.42.38-.41.83-.51 1.1-.51.27 0 .55 0 .79.01.25.01.59-.1.92.7.34.82 1.17 2.85 1.27 3.06.1.21.17.45.03.72-.14.28-.21.45-.41.69-.21.24-.43.54-.62.72-.21.2-.42.42-.18.83.24.41 1.07 1.76 2.3 2.85 1.58 1.41 2.91 1.85 3.32 2.05.41.21.65.17.89-.1.24-.28 1.03-1.2 1.3-1.61.27-.41.55-.34.93-.2.38.14 2.41 1.14 2.82 1.34.41.21.69.31.79.48.1.17.1.99-.24 1.95z"/></svg>
                    </div>
                    <div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <h1 style="color: #fff; font-size: 22px; font-weight: 800; margin: 0; padding: 0; line-height: 1.2;">Broadcast Buddy</h1>
                            <span style="background: #10b981; color: #022c22; font-size: 11px; font-weight: 800; padding: 2px 8px; border-radius: 12px;">v1.3.4</span>
                        </div>
                        <p style="color: #94a3b8; font-size: 13px; margin: 4px 0 0 0;">WhatsApp Order Notifications, Interactive Chat Widgets &amp; Bot Automation</p>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <a href="mailto:<?php echo esc_attr(self::SUPPORT_EMAIL); ?>" class="button" style="background: rgba(255,255,255,0.08); color: #e2e8f0; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; font-weight: 600;">✉️ Support: <?php echo esc_html(self::SUPPORT_EMAIL); ?></a>
                    <a href="https://broadcastbuddy.app/api-config" target="_blank" rel="noopener noreferrer" class="button" style="background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; font-weight: 600;">Developer Hub ↗</a>
                    <a href="https://broadcastbuddy.app/docs" target="_blank" rel="noopener noreferrer" class="button" style="background: #10b981; color: #fff; border: none; border-radius: 8px; font-weight: 700;">API Docs ↗</a>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <h2 class="nav-tab-wrapper" style="border-bottom: 2px solid #e2e8f0; margin-bottom: 24px;">
                <a href="<?php echo esc_url(admin_url('admin.php?page=broadcast-buddy&tab=connection')); ?>" class="nav-tab <?php echo $active_tab === 'connection' ? 'nav-tab-active' : ''; ?>" style="font-weight: 700; border-radius: 8px 8px 0 0;">🔌 Connection &amp; Status</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=broadcast-buddy&tab=widget')); ?>" class="nav-tab <?php echo $active_tab === 'widget' ? 'nav-tab-active' : ''; ?>" style="font-weight: 700; border-radius: 8px 8px 0 0;">💬 Interactive &amp; Hybrid Widget</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=broadcast-buddy&tab=woocommerce')); ?>" class="nav-tab <?php echo $active_tab === 'woocommerce' ? 'nav-tab-active' : ''; ?>" style="font-weight: 700; border-radius: 8px 8px 0 0;">🛒 WooCommerce Alerts</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=broadcast-buddy&tab=testing')); ?>" class="nav-tab <?php echo $active_tab === 'testing' ? 'nav-tab-active' : ''; ?>" style="font-weight: 700; border-radius: 8px 8px 0 0;">🧪 Live Test Sandbox</a>
            </h2>

            <form method="post" action="options.php" id="bb-main-form">

                <!-- TAB 1: Connection & Status -->
                <?php if ($active_tab === 'connection'): ?>
                    <?php settings_fields('broadcast_buddy_connection_group'); ?>
                    <div style="background: #fff; padding: 26px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                        <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 0;">WhatsApp Connection Status</h3>
                        
                        <!-- Real-Time Connection Box -->
                        <div id="bb-status-box" style="padding: 16px 20px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span id="bb-status-dot" style="width: 12px; height: 12px; border-radius: 50%; background: #94a3b8; display: inline-block;"></span>
                                <div>
                                    <strong id="bb-status-text" style="font-size: 14px; color: #334155;">Checking connection...</strong>
                                    <p id="bb-status-detail" style="font-size: 12px; color: #64748b; margin: 2px 0 0 0;">Click below to verify session status with Broadcast Buddy API.</p>
                                </div>
                            </div>
                            <button type="button" id="bb-check-status-btn" class="button button-secondary" style="border-radius: 8px; font-weight: 600;">
                                🔄 Check Status Now
                            </button>
                        </div>

                        <!-- 8-Digit Pairing Code Box (Shown when disconnected) -->
                        <div id="bb-pairing-box" style="display: none; padding: 18px 20px; border-radius: 12px; background: #fefce8; border: 1px solid #fef08a; margin-bottom: 24px;">
                            <h4 style="margin: 0 0 6px 0; color: #854d0e; font-size: 14px;">WhatsApp Disconnected? Link with 8-Digit Phone Pairing Code</h4>
                            <p style="font-size: 12px; color: #a16207; margin: 0 0 12px 0;">Enter your WhatsApp phone number to receive an 8-digit linking code on screen. No QR camera scanning required.</p>
                            <div style="display: flex; gap: 10px; max-width: 480px;">
                                <input type="text" id="bb-pairing-phone" placeholder="e.g. 233240001122" class="regular-text" style="border-radius: 8px;" />
                                <button type="button" id="bb-request-code-btn" class="button button-primary" style="background: #eab308; border-color: #ca8a04; color: #000; font-weight: 700; border-radius: 8px;">
                                    Get 8-Digit Code
                                </button>
                            </div>
                            <div id="bb-code-display" style="display: none; margin-top: 14px; padding: 12px; background: #fff; border-radius: 8px; border: 1px dashed #ca8a04; font-family: monospace; font-size: 20px; font-weight: 900; color: #854d0e; letter-spacing: 4px; text-align: center;"></div>
                        </div>

                        <table class="form-table" style="margin-top: 0;">
                            <tr valign="top">
                                <th scope="row" style="font-weight: 700;">Broadcast Buddy API Key (Session ID)</th>
                                <td>
                                    <input type="text" name="broadcast_buddy_api_key" id="broadcast_buddy_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="e.g. bb_live_..." style="border-radius: 8px; width: 100%; max-width: 480px;" required />
                                    <p class="description">Copy your <b>Live API Key</b> or <b>Sandbox Test Key</b> from your Broadcast Buddy Dashboard.</p>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row" style="font-weight: 700;">Admin WhatsApp Number</th>
                                <td>
                                    <input type="text" name="broadcast_buddy_admin_phone" value="<?php echo esc_attr($admin_phone); ?>" class="regular-text" placeholder="e.g. 233240001122" style="border-radius: 8px; width: 100%; max-width: 480px;" />
                                    <p class="description">WhatsApp phone number with country code (e.g. 233240001122) to receive admin notifications.</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- TAB 2: Interactive & Hybrid Chat Widget -->
                <?php if ($active_tab === 'widget'): ?>
                    <?php settings_fields('broadcast_buddy_widget_group'); ?>
                    <div style="background: #fff; padding: 26px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin: 0;">Interactive, Hybrid &amp; WhatsApp Chat Widget</h3>
                                <p style="font-size: 13px; color: #64748b; margin: 4px 0 0 0;">Deliver automated AI conversational flows directly on-site or route visitors seamlessly to WhatsApp.</p>
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; font-weight: 700; cursor: pointer; background: #f1f5f9; padding: 8px 14px; border-radius: 10px;">
                                <input type="hidden" name="broadcast_buddy_widget_enabled" value="0" />
                                <input type="checkbox" name="broadcast_buddy_widget_enabled" value="1" <?php checked($widget_enabled); ?> />
                                <span>Enable Floating Widget</span>
                            </label>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 30px;">
                            <!-- Widget Settings Form -->
                            <div>
                                <table class="form-table" style="margin: 0;">
                                    <!-- Widget Chat Mode -->
                                    <tr valign="top">
                                        <th scope="row" style="font-weight: 700;">Widget Chat Mode</th>
                                        <td>
                                            <div class="bb-mode-grid">
                                                <label class="bb-mode-card <?php echo $widget_mode === 'whatsapp' ? 'active-mode' : ''; ?>" data-mode="whatsapp">
                                                    <input type="radio" name="broadcast_buddy_widget_mode" value="whatsapp" <?php checked($widget_mode, 'whatsapp'); ?> />
                                                    <div class="bb-mode-title">
                                                        <span>📱 WhatsApp Direct</span>
                                                    </div>
                                                    <div class="bb-mode-desc">Launches WhatsApp app or web directly with prefilled text.</div>
                                                </label>

                                                <label class="bb-mode-card <?php echo $widget_mode === 'webchat' ? 'active-mode' : ''; ?>" data-mode="webchat">
                                                    <input type="radio" name="broadcast_buddy_widget_mode" value="webchat" <?php checked($widget_mode, 'webchat'); ?> />
                                                    <div class="bb-mode-title">
                                                        <span>🤖 Interactive WebChat</span>
                                                    </div>
                                                    <div class="bb-mode-desc">On-page interactive assistant running your automated Bot Flows.</div>
                                                </label>

                                                <label class="bb-mode-card <?php echo $widget_mode === 'hybrid' ? 'active-mode' : ''; ?>" data-mode="hybrid">
                                                    <input type="radio" name="broadcast_buddy_widget_mode" value="hybrid" <?php checked($widget_mode, 'hybrid'); ?> />
                                                    <div class="bb-mode-title">
                                                        <span>⚡ Hybrid (Bot + WA)</span>
                                                    </div>
                                                    <div class="bb-mode-desc">Interactive on-page bot with a 1-click WhatsApp handoff button.</div>
                                                </label>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Bot Flow Selector Section -->
                                    <tr valign="top" id="bb-bot-flow-section" style="<?php echo ($widget_mode === 'whatsapp') ? 'display: none;' : ''; ?>">
                                        <th scope="row" style="font-weight: 700;">
                                            Automated Bot Flow
                                        </th>
                                        <td>
                                            <div style="display: flex; gap: 10px; align-items: center; max-width: 480px;">
                                                <select name="broadcast_buddy_widget_flow_id" id="broadcast_buddy_widget_flow_id" data-current-val="<?php echo esc_attr($widget_flow_id); ?>" style="border-radius: 8px; flex: 1;">
                                                    <option value="">-- Select Automated Flow (or Default Welcome) --</option>
                                                    <?php if (!empty($widget_flow_id)): ?>
                                                        <option value="<?php echo esc_attr($widget_flow_id); ?>" selected>Active Flow ID: <?php echo esc_html($widget_flow_id); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                                <button type="button" id="bb-refresh-flows-btn" class="button button-secondary" style="border-radius: 8px; white-space: nowrap; font-weight: 600;">
                                                    🔄 Refresh Flows
                                                </button>
                                            </div>
                                            <div id="bb-flow-status-msg" style="font-size: 12px; margin-top: 6px; min-height: 18px;"></div>
                                            <p class="description" style="margin-top: 4px;">
                                                Select which Flow from your <a href="https://broadcastbuddy.app/bot-designer" target="_blank" rel="noopener noreferrer" style="color: #10b981; font-weight: 700;">Bot Flow Builder ↗</a> this widget will run when visitors chat.
                                            </p>

                                            <!-- Bot Identity Settings -->
                                            <div style="margin-top: 14px; padding: 14px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
                                                    <div>
                                                        <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Bot Display Name</label>
                                                        <input type="text" name="broadcast_buddy_widget_bot_name" id="broadcast_buddy_widget_bot_name" value="<?php echo esc_attr($widget_bot_name); ?>" placeholder="e.g. Shop Concierge" style="width: 100%; border-radius: 6px;" />
                                                    </div>
                                                    <div>
                                                        <label style="font-size: 11px; font-weight: 700; color: #475569; display: block; margin-bottom: 4px;">Bot Avatar Image URL</label>
                                                        <input type="text" name="broadcast_buddy_widget_bot_avatar" id="broadcast_buddy_widget_bot_avatar" value="<?php echo esc_attr($widget_bot_avatar); ?>" placeholder="https://.../avatar.png" style="width: 100%; border-radius: 6px;" />
                                                    </div>
                                                </div>
                                                <label style="display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: #334155; cursor: pointer;">
                                                    <input type="hidden" name="broadcast_buddy_widget_show_wa_btn" value="0" />
                                                    <input type="checkbox" name="broadcast_buddy_widget_show_wa_btn" id="broadcast_buddy_widget_show_wa_btn" value="1" <?php checked($widget_show_wa_btn); ?> />
                                                    <span>Include WhatsApp button in WebChat header/footer</span>
                                                </label>
                                            </div>
                                        </td>
                                    </tr>

                                    <tr valign="top">
                                        <th scope="row" style="font-weight: 700;">Display Widget On</th>
                                        <td>
                                            <select name="broadcast_buddy_widget_display_on" style="border-radius: 8px; width: 100%; max-width: 380px;">
                                                <option value="all" <?php selected($widget_display_on, 'all'); ?>>Entire Website (All Pages &amp; Blog Posts)</option>
                                                <option value="shop_only" <?php selected($widget_display_on, 'shop_only'); ?>>WooCommerce Shop &amp; Products Only</option>
                                                <option value="posts_only" <?php selected($widget_display_on, 'posts_only'); ?>>Blog Posts &amp; Articles Only</option>
                                                <option value="pages_only" <?php selected($widget_display_on, 'pages_only'); ?>>Static Pages Only</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" style="font-weight: 700;">Widget WhatsApp Phone</th>
                                        <td>
                                            <input type="text" name="broadcast_buddy_widget_phone" id="broadcast_buddy_widget_phone" value="<?php echo esc_attr($widget_phone); ?>" placeholder="e.g. 233240001122" class="regular-text" style="border-radius: 8px; width: 100%; max-width: 380px;" />
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" style="font-weight: 700;">Greeting Card Title</th>
                                        <td>
                                            <input type="text" name="broadcast_buddy_widget_title" id="broadcast_buddy_widget_title" value="<?php echo esc_attr($widget_title); ?>" class="regular-text" style="border-radius: 8px; width: 100%; max-width: 380px;" />
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" style="font-weight: 700;">Greeting Message</th>
                                        <td>
                                            <textarea name="broadcast_buddy_widget_subtitle" id="broadcast_buddy_widget_subtitle" rows="2" class="regular-text" style="border-radius: 8px; width: 100%; max-width: 380px;"><?php echo esc_textarea($widget_subtitle); ?></textarea>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" style="font-weight: 700;">Action Button Text</th>
                                        <td>
                                            <input type="text" name="broadcast_buddy_widget_btn_text" id="broadcast_buddy_widget_btn_text" value="<?php echo esc_attr($widget_btn_text); ?>" class="regular-text" style="border-radius: 8px; width: 100%; max-width: 380px;" />
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" style="font-weight: 700;">Prefilled Bot Trigger Keyword</th>
                                        <td>
                                            <input type="text" name="broadcast_buddy_widget_trigger_text" id="broadcast_buddy_widget_trigger_text" value="<?php echo esc_attr($widget_trigger_text); ?>" class="regular-text" style="border-radius: 8px; width: 100%; max-width: 380px;" />
                                            <p class="description">Message pre-filled in WhatsApp when the visitor clicks "Start chat". Matches keywords in your Bot Flow Builder.</p>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" style="font-weight: 700;">Screen Position &amp; Color</th>
                                        <td>
                                            <div style="display: flex; gap: 12px; align-items: center;">
                                                <select name="broadcast_buddy_widget_position" id="broadcast_buddy_widget_position" style="border-radius: 8px;">
                                                    <option value="bottom-right" <?php selected($widget_position, 'bottom-right'); ?>>Bottom Right</option>
                                                    <option value="bottom-left" <?php selected($widget_position, 'bottom-left'); ?>>Bottom Left</option>
                                                </select>
                                                <input type="color" name="broadcast_buddy_widget_color" id="broadcast_buddy_widget_color" value="<?php echo esc_attr($widget_color); ?>" style="width: 40px; height: 32px; border-radius: 6px; cursor: pointer; border: 1px solid #cbd5e1;" />
                                            </div>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row" style="font-weight: 700;">Auto-Open Greeting Popup</th>
                                        <td>
                                            <select name="broadcast_buddy_widget_auto_open" style="border-radius: 8px; width: 100%; max-width: 380px;">
                                                <option value="0" <?php selected($widget_auto_open, '0'); ?>>Disabled (Open on button click only)</option>
                                                <option value="2000" <?php selected($widget_auto_open, '2000'); ?>>Auto-open after 2 seconds</option>
                                                <option value="3500" <?php selected($widget_auto_open, '3500'); ?>>Auto-open after 3.5 seconds (Recommended)</option>
                                                <option value="6000" <?php selected($widget_auto_open, '6000'); ?>>Auto-open after 6 seconds</option>
                                            </select>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Live Admin Preview Box -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; display: flex; flex-direction: column; justify-content: space-between; position: relative;">
                                <div style="font-size: 11px; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
                                    <span>Live Preview</span>
                                    <span id="bb-preview-bot-badge" style="display: none; background: #10b981; color: #fff; font-size: 9px; padding: 2px 6px; border-radius: 8px; font-weight: 700;">Interactive Flow</span>
                                </div>
                                
                                <div style="margin-top: 10px;">
                                    <!-- Card Preview -->
                                    <div id="bb-preview-card" style="background: #fff; border-radius: 20px; padding: 16px; box-shadow: 0 12px 30px rgba(0,0,0,0.12); border: 1px solid #f1f5f9; margin-bottom: 14px;">
                                        <div style="display: flex; gap: 10px; align-items: flex-start;">
                                            <div id="bb-preview-icon" style="width: 38px; height: 38px; border-radius: 50%; background: <?php echo esc_attr($widget_color); ?>; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 10px rgba(37,211,102,0.3);">
                                                <svg viewBox="0 0 32 32" width="22" height="22" fill="currentColor" style="display:block;"><path d="M16 2C8.28 2 2 8.28 2 16c0 2.7.76 5.23 2.08 7.39L2.5 29.5l6.32-1.54C10.9 29.18 13.37 30 16 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm8.17 19.86c-.34.96-1.7 1.83-2.77 2.06-.73.16-1.69.29-4.89-1.04-4.1-1.7-6.73-5.88-6.94-6.15-.2-.27-1.67-2.22-1.67-4.24 0-2.02 1.05-3.01 1.43-3.42.38-.41.83-.51 1.1-.51.27 0 .55 0 .79.01.25.01.59-.1.92.7.34.82 1.17 2.85 1.27 3.06.1.21.17.45.03.72-.14.28-.21.45-.41.69-.21.24-.43.54-.62.72-.21.2-.42.42-.18.83.24.41 1.07 1.76 2.3 2.85 1.58 1.41 2.91 1.85 3.32 2.05.41.21.65.17.89-.1.24-.28 1.03-1.2 1.3-1.61.27-.41.55-.34.93-.2.38.14 2.41 1.14 2.82 1.34.41.21.69.31.79.48.1.17.1.99-.24 1.95z"/></svg>
                                            </div>
                                            <div>
                                                <div id="bb-preview-title" style="font-weight: 800; font-size: 15px; color: #0f172a; line-height: 1.2;"><?php echo esc_html($widget_title); ?></div>
                                                <div id="bb-preview-subtitle" style="font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.35;"><?php echo esc_html($widget_subtitle); ?></div>
                                            </div>
                                        </div>

                                        <!-- Interactive Flow Options Mock -->
                                        <div id="bb-preview-interactive-options" style="display: none; margin-top: 12px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
                                            <div style="font-size: 11px; font-weight: 700; color: #64748b; margin-bottom: 6px;">⚡ Quick Options:</div>
                                            <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                                <span style="background: #f1f5f9; color: #334155; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 12px; border: 1px solid #e2e8f0;">Track Order</span>
                                                <span style="background: #f1f5f9; color: #334155; font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 12px; border: 1px solid #e2e8f0;">Browse Products</span>
                                            </div>
                                        </div>

                                        <!-- WhatsApp Handoff Mock -->
                                        <div id="bb-preview-wa-handoff" style="display: none; margin-top: 10px; padding: 6px 10px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; font-size: 11px; color: #166534; font-weight: 600;">
                                            💬 WhatsApp Live Handoff Active
                                        </div>

                                        <div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #f8fafc;">
                                            <span id="bb-preview-btn" style="color: <?php echo esc_attr($widget_color); ?>; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;"><?php echo esc_html($widget_btn_text); ?></span>
                                        </div>
                                    </div>

                                    <!-- Button Preview -->
                                    <div style="display: flex; justify-content: flex-end;">
                                        <div id="bb-preview-circle" style="width: 52px; height: 52px; border-radius: 50%; background: <?php echo esc_attr($widget_color); ?>; color: #fff; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 20px rgba(37,211,102,0.4); cursor: pointer;">
                                            <svg viewBox="0 0 32 32" width="28" height="28" fill="currentColor" style="display:block;"><path d="M16 2C8.28 2 2 8.28 2 16c0 2.7.76 5.23 2.08 7.39L2.5 29.5l6.32-1.54C10.9 29.18 13.37 30 16 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm8.17 19.86c-.34.96-1.7 1.83-2.77 2.06-.73.16-1.69.29-4.89-1.04-4.1-1.7-6.73-5.88-6.94-6.15-.2-.27-1.67-2.22-1.67-4.24 0-2.02 1.05-3.01 1.43-3.42.38-.41.83-.51 1.1-.51.27 0 .55 0 .79.01.25.01.59-.1.92.7.34.82 1.17 2.85 1.27 3.06.1.21.17.45.03.72-.14.28-.21.45-.41.69-.21.24-.43.54-.62.72-.21.2-.42.42-.18.83.24.41 1.07 1.76 2.3 2.85 1.58 1.41 2.91 1.85 3.32 2.05.41.21.65.17.89-.1.24-.28 1.03-1.2 1.3-1.61.27-.41.55-.34.93-.2.38.14 2.41 1.14 2.82 1.34.41.21.69.31.79.48.1.17.1.99-.24 1.95z"/></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Shortcode Info -->
                        <div style="margin-top: 24px; padding: 14px 18px; border-radius: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; font-size: 12px; color: #166534;">
                            💡 <b>Embed in any Post or Blog Article:</b> You can also insert a chat button anywhere in your content using the shortcode: <code style="background: #fff; padding: 2px 6px; border-radius: 4px; font-weight: 700;">[broadcast_buddy_whatsapp_button text="Chat with Support on WhatsApp"]</code>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- TAB 3: WooCommerce Order Alerts -->
                <?php if ($active_tab === 'woocommerce'): ?>
                    <?php settings_fields('broadcast_buddy_woocommerce_group'); ?>
                    <div style="background: #fff; padding: 26px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                        <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 0;">Automated WooCommerce WhatsApp Notifications</h3>
                        <p style="font-size: 13px; color: #64748b; margin: 4px 0 20px 0;">Available merge tags: <code>{order_id}</code>, <code>{first_name}</code>, <code>{customer_name}</code>, <code>{order_total}</code>, <code>{items_list}</code>, <code>{site_name}</code></p>

                        <table class="form-table">
                            <!-- Admin New Order -->
                            <tr valign="top">
                                <th scope="row" style="font-weight: 700;">
                                    Admin New Order Alert<br/>
                                    <label style="font-weight: normal; font-size: 12px;">
                                        <input type="hidden" name="broadcast_buddy_notify_admin_new" value="0" />
                                        <input type="checkbox" name="broadcast_buddy_notify_admin_new" value="1" <?php checked($notify_admin_new); ?> /> Enable Alert
                                    </label>
                                </th>
                                <td>
                                    <textarea name="broadcast_buddy_template_admin_new" rows="4" class="large-text" style="border-radius: 8px;"><?php echo esc_textarea($template_admin_new); ?></textarea>
                                </td>
                            </tr>

                            <!-- Customer Order Processing -->
                            <tr valign="top">
                                <th scope="row" style="font-weight: 700;">
                                    Customer Order Processing<br/>
                                    <label style="font-weight: normal; font-size: 12px;">
                                        <input type="hidden" name="broadcast_buddy_notify_customer_processing" value="0" />
                                        <input type="checkbox" name="broadcast_buddy_notify_customer_processing" value="1" <?php checked($notify_customer_processing); ?> /> Enable Alert
                                    </label>
                                </th>
                                <td>
                                    <textarea name="broadcast_buddy_template_customer_processing" rows="3" class="large-text" style="border-radius: 8px;"><?php echo esc_textarea($template_customer_processing); ?></textarea>
                                </td>
                            </tr>

                            <!-- Customer Order Completed -->
                            <tr valign="top">
                                <th scope="row" style="font-weight: 700;">
                                    Customer Order Completed<br/>
                                    <label style="font-weight: normal; font-size: 12px;">
                                        <input type="hidden" name="broadcast_buddy_notify_customer_completed" value="0" />
                                        <input type="checkbox" name="broadcast_buddy_notify_customer_completed" value="1" <?php checked($notify_customer_completed); ?> /> Enable Alert
                                    </label>
                                </th>
                                <td>
                                    <textarea name="broadcast_buddy_template_customer_completed" rows="3" class="large-text" style="border-radius: 8px;"><?php echo esc_textarea($template_customer_completed); ?></textarea>
                                </td>
                            </tr>

                            <!-- Customer Order Cancelled -->
                            <tr valign="top">
                                <th scope="row" style="font-weight: 700;">
                                    Customer Order Cancelled<br/>
                                    <label style="font-weight: normal; font-size: 12px;">
                                        <input type="hidden" name="broadcast_buddy_notify_customer_cancelled" value="0" />
                                        <input type="checkbox" name="broadcast_buddy_notify_customer_cancelled" value="1" <?php checked($notify_customer_cancelled); ?> /> Enable Alert
                                    </label>
                                </th>
                                <td>
                                    <textarea name="broadcast_buddy_template_customer_cancelled" rows="3" class="large-text" style="border-radius: 8px;"><?php echo esc_textarea($template_customer_cancelled); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- TAB 4: Live Test Sandbox -->
                <?php if ($active_tab === 'testing'): ?>
                    <div style="background: #fff; padding: 26px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
                        <h3 style="font-size: 16px; font-weight: 700; color: #0f172a; margin-top: 0;">Live WhatsApp Message Sandbox</h3>
                        <p style="font-size: 13px; color: #64748b; margin: 4px 0 20px 0;">Test sending a real message through your connected WhatsApp hotline right from WordPress.</p>

                        <div style="max-width: 580px;">
                            <div style="margin-bottom: 14px;">
                                <label style="display: block; font-weight: 700; font-size: 12px; margin-bottom: 4px;">Recipient WhatsApp Phone Number</label>
                                <input type="text" id="bb-test-recipient" placeholder="e.g. 233240001122" value="<?php echo esc_attr($admin_phone); ?>" class="regular-text" style="width: 100%; border-radius: 8px;" />
                            </div>

                            <div style="margin-bottom: 14px;">
                                <label style="display: block; font-weight: 700; font-size: 12px; margin-bottom: 4px;">Message Content</label>
                                <textarea id="bb-test-message" rows="4" class="large-text" style="width: 100%; border-radius: 8px;">🚀 Hello from Broadcast Buddy! Your WhatsApp connection is working perfectly.</textarea>
                            </div>

                            <button type="button" id="bb-send-test-btn" class="button button-primary" style="background: #10b981; border: none; font-weight: 700; padding: 6px 20px; border-radius: 8px; height: 38px;">
                                📤 Send Live Test WhatsApp
                            </button>

                            <div id="bb-test-result" style="display: none; margin-top: 20px; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 12px; line-height: 1.5; white-space: pre-wrap;"></div>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 24px; display: flex; justify-content: space-between; align-items: center;">
                    <?php submit_button('💾 Save Settings', 'primary', 'submit', false, array('style' => 'background: #0f172a; border-color: #0f172a; font-weight: 700; padding: 6px 24px; border-radius: 8px;')); ?>
                    <span style="font-size: 12px; color: #64748b;">
                        Need technical support? Contact <a href="mailto:<?php echo esc_attr(self::SUPPORT_EMAIL); ?>" style="color: #10b981; font-weight: 700; text-decoration: none;"><?php echo esc_html(self::SUPPORT_EMAIL); ?></a>
                    </span>
                </div>
            </form>
        </div>
        <?php
    }

    /* ─── AJAX: Fetch Bot Flows ─── */
    public function ajax_fetch_flows() {
        check_ajax_referer('broadcast_buddy_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized user'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        if (empty($api_key)) {
            $api_key = $this->get_opt('broadcast_buddy_api_key', 'bb_api_key', '');
        }

        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API Key required. Please configure and save your API Key in the Connection tab first.'));
        }

        $url = self::API_URL . '/bot/flows?sessionId=' . urlencode($api_key);
        $response = wp_remote_get($url, array(
            'timeout'   => 15,
            'sslverify' => false,
            'headers'   => array(
                'Accept'    => 'application/json',
                'x-api-key' => $api_key
            )
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['success']) && $body['success'] && isset($body['flows'])) {
            wp_send_json_success(array('flows' => $body['flows']));
        } else {
            $msg = isset($body['error']) ? $body['error'] : (isset($body['message']) ? $body['message'] : 'Failed to fetch flows from account');
            wp_send_json_error(array('message' => $msg));
        }
    }

    /* ─── AJAX: Check Live Session Status ─── */
    public function ajax_check_status() {
        check_ajax_referer('broadcast_buddy_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized user'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : $this->api_key;
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API Key required'));
        }

        $url = self::API_URL . '/session/status/' . urlencode($api_key);
        $response = wp_remote_get($url, array('timeout' => 10));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status = isset($body['status']) ? $body['status'] : '';
        $connected = ($status === 'CONNECTED' || $status === 'CONNECTED_STANDBY' || (isset($body['state']) && $body['state'] === 'CONNECTED'));

        wp_send_json_success(array(
            'connected' => $connected,
            'status' => $status,
            'phone' => isset($body['user']) ? $body['user'] : '',
            'message' => $connected ? 'Hotline is connected.' : 'Session is disconnected.'
        ));
    }

    /* ─── AJAX: Request 8-Digit Pairing Code ─── */
    public function ajax_request_pairing() {
        check_ajax_referer('broadcast_buddy_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized user'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : $this->api_key;
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        if (empty($api_key) || empty($phone)) {
            wp_send_json_error(array('message' => 'API Key and Phone number required'));
        }

        $url = self::API_URL . '/session/requestPairingCode/' . urlencode($api_key);
        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array('phoneNumber' => $phone)),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['pairingCode']) || isset($body['code'])) {
            wp_send_json_success(array('pairingCode' => isset($body['pairingCode']) ? $body['pairingCode'] : $body['code']));
        } else {
            wp_send_json_error(array('message' => isset($body['error']) ? $body['error'] : 'Failed to generate code'));
        }
    }

    /* ─── AJAX: Send Live Test Message ─── */
    public function ajax_send_test() {
        check_ajax_referer('broadcast_buddy_admin_action', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized user'));
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : $this->api_key;
        $recipient = isset($_POST['recipient']) ? sanitize_text_field(wp_unslash($_POST['recipient'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if (empty($api_key) || empty($recipient) || empty($message)) {
            wp_send_json_error(array('message' => 'All fields required'));
        }

        $result = $this->send_whatsapp_custom($api_key, $recipient, $message);
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['data']);
        }
    }

    /* ─── WhatsApp Send Helper ─── */
    public function send_whatsapp($phone, $message) {
        $res = $this->send_whatsapp_custom($this->api_key, $phone, $message);
        return $res['success'];
    }

    public function send_whatsapp_custom($api_key, $phone, $message) {
        if (empty($api_key)) return array('success' => false, 'data' => array('message' => 'No API key'));

        $clean_phone = preg_replace('/[^0-9]/', '', $phone);
        $chat_id = strpos($clean_phone, '@') !== false ? $clean_phone : $clean_phone . '@c.us';
        $endpoint = self::API_URL . '/client/sendMessage/' . urlencode($api_key);

        $body = wp_json_encode(array(
            'chatId' => $chat_id,
            'contentType' => 'string',
            'content' => $message
        ));

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'x-api-key' => $api_key
            ),
            'body' => $body,
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            return array('success' => false, 'data' => array('message' => $response->get_error_message()));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $is_ok = (isset($data['status']) && ($data['status'] === 'success' || $data['status'] === true)) || (isset($data['success']) && $data['success'] === true);
        return array('success' => $is_ok, 'data' => $data);
    }

    /* ─── Frontend Widget Enqueue ─── */
    public function enqueue_frontend_widget() {
        $raw_enabled = $this->get_opt('broadcast_buddy_widget_enabled', 'bb_widget_enabled', '1');
        if ($raw_enabled === '0' || $raw_enabled === 'off') {
            return;
        }

        $display_on = $this->get_opt('broadcast_buddy_widget_display_on', 'bb_widget_display_on', 'all');
        if ($display_on === 'shop_only' && function_exists('is_woocommerce') && !is_woocommerce() && !is_cart() && !is_checkout()) {
            return;
        }
        if ($display_on === 'posts_only' && !is_single() && !is_home() && !is_archive()) {
            return;
        }
        if ($display_on === 'pages_only' && !is_page()) {
            return;
        }

        $api_key = $this->get_opt('broadcast_buddy_api_key', 'bb_api_key', '');
        $admin_phone = $this->get_opt('broadcast_buddy_admin_phone', 'bb_admin_phone', '');
        $phone = $this->get_opt('broadcast_buddy_widget_phone', 'bb_widget_phone', $admin_phone);
        if (empty($phone)) $phone = $admin_phone;

        $mode = $this->get_opt('broadcast_buddy_widget_mode', 'bb_widget_mode', 'whatsapp');
        $flow_id = $this->get_opt('broadcast_buddy_widget_flow_id', 'bb_widget_flow_id', '');
        $bot_name = $this->get_opt('broadcast_buddy_widget_bot_name', 'bb_widget_bot_name', 'Assistant');
        $bot_avatar = $this->get_opt('broadcast_buddy_widget_bot_avatar', 'bb_widget_bot_avatar', '');
        $raw_show_wa = $this->get_opt('broadcast_buddy_widget_show_wa_btn', 'bb_widget_show_wa_btn', '1');
        $show_wa_btn = ($raw_show_wa !== '0' && $raw_show_wa !== 'off');

        $title = $this->get_opt('broadcast_buddy_widget_title', 'bb_widget_title', 'Hi there!');
        if (trim($title) === '') $title = 'Hi there!';

        $subtitle = $this->get_opt('broadcast_buddy_widget_subtitle', 'bb_widget_subtitle', 'Have a question? Chat with us on WhatsApp.');
        if (trim($subtitle) === '') $subtitle = 'Have a question? Chat with us on WhatsApp.';

        $btn_text = $this->get_opt('broadcast_buddy_widget_btn_text', 'bb_widget_btn_text', 'Start chat →');
        if (trim($btn_text) === '') $btn_text = 'Start chat →';

        $trigger_text = $this->get_opt('broadcast_buddy_widget_trigger_text', 'bb_widget_trigger_text', 'Hello! I have an inquiry.');
        if (trim($trigger_text) === '') $trigger_text = 'Hello! I have an inquiry.';

        $position = $this->get_opt('broadcast_buddy_widget_position', 'bb_widget_position', 'bottom-right');
        if (empty($position)) $position = 'bottom-right';

        $color = $this->get_opt('broadcast_buddy_widget_color', 'bb_widget_color', '#25D366');
        if (empty($color)) $color = '#25D366';

        $auto_open = $this->get_opt('broadcast_buddy_widget_auto_open', 'bb_widget_auto_open', '3500');
        if ($auto_open === '') $auto_open = '3500';

        wp_register_style(
            'broadcast-buddy-widget',
            plugins_url('broadcastbuddy-widget.css', __FILE__),
            array(),
            '1.3.5'
        );
        wp_enqueue_style('broadcast-buddy-widget');

        $inline_css = ':root { --bb-color: ' . esc_attr($color) . '; }';
        wp_add_inline_style('broadcast-buddy-widget', $inline_css);

        wp_register_script(
            'broadcast-buddy-widget',
            plugins_url('broadcastbuddy-widget.js', __FILE__),
            array(),
            '1.3.5',
            true
        );

        $widget_config = array(
            'sessionId'          => $api_key,
            'mode'               => $mode,
            'flowId'             => !empty($flow_id) ? (is_numeric($flow_id) ? intval($flow_id) : $flow_id) : null,
            'botName'            => $bot_name,
            'botAvatar'          => $bot_avatar,
            'showWhatsAppButton' => $show_wa_btn,
            'phone'              => $phone,
            'title'              => $title,
            'message'            => $subtitle,
            'btnText'            => $btn_text,
            'triggerText'        => $trigger_text,
            'position'           => $position,
            'color'              => $color,
            'autoOpen'           => intval($auto_open),
            'apiUrl'             => 'https://broadcastbuddy.app'
        );

        wp_localize_script('broadcast-buddy-widget', 'BroadcastBuddyWidgetConfig', $widget_config);
        wp_enqueue_script('broadcast-buddy-widget');
    }

    /* ─── Shortcode for In-Page WhatsApp Buttons ─── */
    public function shortcode_chat_button($atts) {
        $a = shortcode_atts(array(
            'phone' => $this->get_opt('broadcast_buddy_widget_phone', 'bb_widget_phone', $this->get_opt('broadcast_buddy_admin_phone', 'bb_admin_phone', '')),
            'text' => 'Chat on WhatsApp',
            'message' => 'Hello! I have a question about this page.',
            'color' => '#25D366'
        ), $atts);

        $phone_clean = preg_replace('/[^0-9]/', '', $a['phone']);
        $url = 'https://wa.me/' . $phone_clean . '?text=' . rawurlencode($a['message']);

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:8px;background-color:%s;color:#ffffff;padding:10px 18px;border-radius:24px;text-decoration:none;font-weight:700;font-size:14px;box-shadow:0 4px 12px rgba(37,211,102,0.3);"><span>💬</span><span>%s</span></a>',
            esc_url($url),
            esc_attr($a['color']),
            esc_html($a['text'])
        );
    }

    /* ─── Template Placeholder Replacement ─── */
    private function parse_template($template, $order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = $item->get_name() . ' (x' . $item->get_quantity() . ')';
        }

        $placeholders = array(
            '{order_id}' => $order->get_id(),
            '{first_name}' => $order->get_billing_first_name(),
            '{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            '{order_total}' => html_entity_decode(wp_strip_all_tags($order->get_formatted_order_total())),
            '{items_list}' => implode(', ', $items),
            '{site_name}' => get_bloginfo('name')
        );

        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    /* ─── WooCommerce Notification Triggers ─── */
    public function notify_order_processing($order_id) {
        if ($this->get_opt('broadcast_buddy_notify_customer_processing', 'bb_notify_customer_processing', '1') !== '1') return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $phone = $order->get_billing_phone();
        $template = $this->get_opt('broadcast_buddy_template_customer_processing', 'bb_template_customer_processing', '');
        $msg = $this->parse_template($template, $order);
        $this->send_whatsapp($phone, $msg);
    }

    public function notify_order_completed($order_id) {
        if ($this->get_opt('broadcast_buddy_notify_customer_completed', 'bb_notify_customer_completed', '1') !== '1') return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $phone = $order->get_billing_phone();
        $template = $this->get_opt('broadcast_buddy_template_customer_completed', 'bb_template_customer_completed', '');
        $msg = $this->parse_template($template, $order);
        $this->send_whatsapp($phone, $msg);
    }

    public function notify_order_cancelled($order_id) {
        if ($this->get_opt('broadcast_buddy_notify_customer_cancelled', 'bb_notify_customer_cancelled', '0') !== '1') return;
        $order = wc_get_order($order_id);
        if (!$order) return;

        $phone = $order->get_billing_phone();
        $template = $this->get_opt('broadcast_buddy_template_customer_cancelled', 'bb_template_customer_cancelled', '');
        $msg = $this->parse_template($template, $order);
        $this->send_whatsapp($phone, $msg);
    }

    public function notify_admin_new_order($order_id) {
        if ($this->get_opt('broadcast_buddy_notify_admin_new', 'bb_notify_admin_new', '1') !== '1') return;
        $admin_phone = $this->get_opt('broadcast_buddy_admin_phone', 'bb_admin_phone', '');
        if (empty($admin_phone)) return;

        $order = wc_get_order($order_id);
        if (!$order) return;

        $template = $this->get_opt('broadcast_buddy_template_admin_new', 'bb_template_admin_new', '');
        $msg = $this->parse_template($template, $order);
        $this->send_whatsapp($admin_phone, $msg);
    }
}

new BroadcastBuddy();
