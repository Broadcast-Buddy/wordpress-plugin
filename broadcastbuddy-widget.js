/**
 * Broadcast Buddy - WhatsApp & Interactive WebChat Widget
 * 
 * Source Code: https://github.com/Broadcast-Buddy/wordpress-plugin
 * File: src/plugins/broadcastbuddy-widget.js
 * Stylesheet: src/plugins/broadcastbuddy-widget.css
 * License: MIT (https://opensource.org/licenses/MIT)
 * 
 * Written in 100% human-readable, unminified Vanilla JavaScript.
 * This is the original source code — no build tools, compilers, or minifiers are used.
 * 
 * Embed usage:
 * <link rel="stylesheet" href="https://broadcastbuddy.app/plugins/broadcastbuddy-widget.css">
 * <script 
 *   src="https://broadcastbuddy.app/plugins/broadcastbuddy-widget.js" 
 *   data-session-id="bb_live_xxx"
 *   data-mode="webchat"
 *   data-phone="233240001122" 
 *   data-title="Hi there!" 
 *   data-message="Have a question? Chat with our team or our AI bot."
 *   data-btn-text="Start chat →"
 *   data-position="bottom-right"
 *   data-color="#25D366"
 *   data-auto-open="3500"
 *   async>
 * </script>
 */
(function () {
  'use strict';

  if (window.__bb_widget_initialized) return;
  window.__bb_widget_initialized = true;

  // Find script element & extract config attributes
  var scriptTag = document.currentScript || document.querySelector('script[src*="broadcastbuddy-widget.js"]') || (function () {
    var scripts = document.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
      if (scripts[i].src && (scripts[i].src.indexOf('broadcastbuddy-widget.js') !== -1 || scripts[i].getAttribute('data-session-id'))) {
        return scripts[i];
      }
    }
    return null;
  })();

  var globalConfig = window.BroadcastBuddyWidgetConfig || {};

  var scriptOrigin = '';
  if (scriptTag && scriptTag.src) {
    try {
      scriptOrigin = new URL(scriptTag.src, window.location.href).origin;
    } catch (e) {}
  }

  var config = {
    sessionId: (scriptTag && scriptTag.getAttribute('data-session-id')) || globalConfig.sessionId || '',
    mode: (scriptTag && scriptTag.getAttribute('data-mode')) || globalConfig.mode || 'whatsapp', // 'whatsapp' | 'webchat' | 'hybrid'
    flowId: (scriptTag && scriptTag.getAttribute('data-flow-id')) || globalConfig.flowId || null,
    botName: (scriptTag && scriptTag.getAttribute('data-bot-name')) || globalConfig.botName || 'Assistant',
    botAvatar: (scriptTag && scriptTag.getAttribute('data-bot-avatar')) || globalConfig.botAvatar || '',
    showWhatsAppButton: (scriptTag && scriptTag.getAttribute('data-show-wa')) !== 'false' && globalConfig.showWhatsAppButton !== false,
    hideBranding: (scriptTag && scriptTag.getAttribute('data-hide-branding')) === 'true' || globalConfig.hideBranding || false,
    phone: (scriptTag && scriptTag.getAttribute('data-phone')) || globalConfig.phone || '',
    title: (scriptTag && scriptTag.getAttribute('data-title')) || globalConfig.title || 'Hi there!',
    message: (scriptTag && scriptTag.getAttribute('data-message')) || globalConfig.message || 'Have a question? Chat with our team on WhatsApp.',
    btnText: (scriptTag && scriptTag.getAttribute('data-btn-text')) || globalConfig.btnText || 'Start chat →',
    triggerText: (scriptTag && scriptTag.getAttribute('data-trigger-text')) || globalConfig.triggerText || 'Hello! I need assistance.',
    position: (scriptTag && scriptTag.getAttribute('data-position')) || globalConfig.position || 'bottom-right',
    color: (scriptTag && scriptTag.getAttribute('data-color')) || globalConfig.color || '#25D366',
    autoOpen: parseInt((scriptTag && scriptTag.getAttribute('data-auto-open')) || globalConfig.autoOpen || '3500', 10),
    apiUrl: (scriptTag && scriptTag.getAttribute('data-api-url')) || globalConfig.apiUrl || scriptOrigin || window.location.origin
  };

  // SVG Icons (Clean vector graphics - no emojis)
  var WA_SVG = '<svg viewBox="0 0 32 32" width="28" height="28" fill="currentColor" style="display:block;"><path d="M16 2C8.28 2 2 8.28 2 16c0 2.7.76 5.23 2.08 7.39L2.5 29.5l6.32-1.54C10.9 29.18 13.37 30 16 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm8.17 19.86c-.34.96-1.7 1.83-2.77 2.06-.73.16-1.69.29-4.89-1.04-4.1-1.7-6.73-5.88-6.94-6.15-.2-.27-1.67-2.22-1.67-4.24 0-2.02 1.05-3.01 1.43-3.42.38-.41.83-.51 1.1-.51.27 0 .55 0 .79.01.25.01.59-.1.92.7.34.82 1.17 2.85 1.27 3.06.1.21.17.45.03.72-.14.28-.21.45-.41.69-.21.24-.43.54-.62.72-.21.2-.42.42-.18.83.24.41 1.07 1.76 2.3 2.85 1.58 1.41 2.91 1.85 3.32 2.05.41.21.65.17.89-.1.24-.28 1.03-1.2 1.3-1.61.27-.41.55-.34.93-.2.38.14 2.41 1.14 2.82 1.34.41.21.69.31.79.48.1.17.1.99-.24 1.95z"/></svg>';
  var CLOSE_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
  var SEND_SVG = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>';
  var RESTART_SVG = '<svg viewBox="0 0 24 24" width="15" height="15" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>';
  var BOT_SVG = '<svg viewBox="0 0 24 24" width="22" height="22" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="10" rx="2"></rect><circle cx="12" cy="5" r="2"></circle><path d="M12 7v4"></path><line x1="8" y1="16" x2="8" y2="16"></line><line x1="16" y1="16" x2="16" y2="16"></line></svg>';
  var WA_SMALL_SVG = '<svg viewBox="0 0 32 32" width="16" height="16" fill="currentColor"><path d="M16 2C8.28 2 2 8.28 2 16c0 2.7.76 5.23 2.08 7.39L2.5 29.5l6.32-1.54C10.9 29.18 13.37 30 16 30c7.72 0 14-6.28 14-14S23.72 2 16 2zm8.17 19.86c-.34.96-1.7 1.83-2.77 2.06-.73.16-1.69.29-4.89-1.04-4.1-1.7-6.73-5.88-6.94-6.15-.2-.27-1.67-2.22-1.67-4.24 0-2.02 1.05-3.01 1.43-3.42.38-.41.83-.51 1.1-.51.27 0 .55 0 .79.01.25.01.59-.1.92.7.34.82 1.17 2.85 1.27 3.06.1.21.17.45.03.72-.14.28-.21.45-.41.69-.21.24-.43.54-.62.72-.21.2-.42.42-.18.83.24.41 1.07 1.76 2.3 2.85 1.58 1.41 2.91 1.85 3.32 2.05.41.21.65.17.89-.1.24-.28 1.03-1.2 1.3-1.61.27-.41.55-.34.93-.2.38.14 2.41 1.14 2.82 1.34.41.21.69.31.79.48.1.17.1.99-.24 1.95z"/></svg>';

  // Unique Visitor ID
  var visitorId = '';
  try {
    var storageKey = 'bb_visitor_' + (config.sessionId || 'default');
    visitorId = localStorage.getItem(storageKey);
    if (!visitorId) {
      visitorId = 'v_' + Math.random().toString(36).substring(2, 10) + Date.now().toString(36);
      localStorage.setItem(storageKey, visitorId);
    }
  } catch (e) {
    visitorId = 'v_' + Math.random().toString(36).substring(2, 10);
  }

  // Record tracking event
  function trackEvent(eventType) {
    if (!config.sessionId) return;
    try {
      var dateKey = new Date().toISOString().split('T')[0];
      var storageKey = '__bb_imp_' + config.sessionId + '_' + dateKey;

      if (eventType === 'impression') {
        try {
          if (sessionStorage.getItem(storageKey) || localStorage.getItem(storageKey)) return;
          sessionStorage.setItem(storageKey, '1');
          localStorage.setItem(storageKey, '1');
        } catch (e) { }
      }

      var trackUrl = config.apiUrl.replace(/\/+$/, '') + '/api/v1/widget/track/' + encodeURIComponent(config.sessionId);
      var payload = JSON.stringify({ event: eventType, referrer: window.location.href, userAgent: navigator.userAgent });
      if (navigator.sendBeacon) {
        navigator.sendBeacon(trackUrl, payload);
      } else {
        fetch(trackUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: payload,
          mode: 'cors'
        }).catch(function () { });
      }
    } catch (e) { }
  }

  function hexToRgba(hex, alpha) {
    if (!hex || typeof hex !== 'string') return 'rgba(79, 70, 229, ' + alpha + ')';
    var clean = hex.replace('#', '').trim();
    if (clean.length === 3) {
      clean = clean[0] + clean[0] + clean[1] + clean[1] + clean[2] + clean[2];
    }
    var r = parseInt(clean.substring(0, 2), 16);
    var g = parseInt(clean.substring(2, 4), 16);
    var b = parseInt(clean.substring(4, 6), 16);
    if (isNaN(r) || isNaN(g) || isNaN(b)) return 'rgba(79, 70, 229, ' + alpha + ')';
    return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
  }

  // Dynamic Theme & Positioning Scoping via CSS Variables
  function applyThemeVariables() {
    if (!container) return;
    if (config.position === 'bottom-left') {
      container.classList.add('bb-pos-left');
    } else {
      container.classList.remove('bb-pos-left');
    }
    container.style.setProperty('--bb-color', config.color);
    container.style.setProperty('--bb-agent-bg', hexToRgba(config.color, 0.08));
    container.style.setProperty('--bb-agent-border', hexToRgba(config.color, 0.28));
    container.style.setProperty('--bb-agent-shadow', hexToRgba(config.color, 0.12));
    container.style.setProperty('--bb-agent-avatar-border', hexToRgba(config.color, 0.35));
    container.style.setProperty('--bb-agent-avatar-bg', hexToRgba(config.color, 0.15));
    container.style.setProperty('--bb-system-pill-bg', hexToRgba(config.color, 0.06));
    container.style.setProperty('--bb-system-pill-border', hexToRgba(config.color, 0.2));
  }

  // Ensure external widget stylesheet link exists if not already present
  if (!document.getElementById('bb-widget-css') && !document.querySelector('link[href*="broadcastbuddy-widget.css"]')) {
    var cssLink = document.createElement('link');
    cssLink.id = 'bb-widget-css';
    cssLink.rel = 'stylesheet';
    var baseUrl = scriptOrigin || config.apiUrl || 'https://broadcastbuddy.app';
    cssLink.href = baseUrl.replace(/\/+$/, '') + '/plugins/broadcastbuddy-widget.css';
    document.head.appendChild(cssLink);
  }

  // Create Root Elements
  var container = document.createElement('div');
  container.id = 'bb-widget-container';

  var card = document.createElement('div');
  card.id = 'bb-widget-card';

  var btnWrapper = document.createElement('div');
  btnWrapper.id = 'bb-widget-btn-wrapper';

  var pulse = document.createElement('div');
  pulse.id = 'bb-widget-pulse';

  var btn = document.createElement('button');
  btn.id = 'bb-widget-btn';
  btn.setAttribute('aria-label', 'Chat with us');
  btn.innerHTML = WA_SVG;

  btnWrapper.appendChild(pulse);
  btnWrapper.appendChild(btn);
  container.appendChild(card);
  container.appendChild(btnWrapper);
  applyThemeVariables();

  var isOpen = false;
  var isFetching = false;
  var chatMessages = [];

  function getBrandingHtml() {
    if (config.hideBranding === true || config.hideBranding === 'true' || config.hideBranding === 1) return '';
    return '<div class="bb-branding-footer"><a class="bb-branding-link" href="https://broadcastbuddy.app" target="_blank" rel="noopener noreferrer">Powered by <strong>Broadcast Buddy</strong></a></div>';
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatText(str) {
    if (!str) return '';
    var escaped = escapeHtml(str);
    // Bold *text*
    escaped = escaped.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
    // Italic _text_
    escaped = escaped.replace(/_([^_]+)_/g, '<em>$1</em>');
    // Newlines
    escaped = escaped.replace(/\n/g, '<br/>');
    return escaped;
  }

  function openWhatsApp(customText) {
    trackEvent('click');
    var phoneClean = (config.phone || '').replace(/[^0-9]/g, '');
    var textEncoded = encodeURIComponent(customText || config.triggerText || '');
    var waUrl = phoneClean
      ? 'https://wa.me/' + phoneClean + (textEncoded ? '?text=' + textEncoded : '')
      : 'https://wa.me/?text=' + textEncoded;
    window.open(waUrl, '_blank', 'noopener,noreferrer');
  }

  function toggleCard() {
    isOpen = !isOpen;
    if (isOpen) {
      card.classList.add('bb-open');
      container.classList.add('bb-is-open');
      if (config.mode === 'webchat' || config.mode === 'hybrid') {
        if (chatMessages.length === 0) {
          initWebChat();
        }
        startLiveSync();
      }
    } else {
      card.classList.remove('bb-open');
      container.classList.remove('bb-is-open');
      stopLiveSync();
    }
  }

  // Render Direct Launcher
  function renderDirectCard() {
    card.innerHTML = [
      '<div class="bb-header">',
      '  <div class="bb-header-left">',
      '    <div class="bb-avatar">' + WA_SVG + '</div>',
      '    <div class="bb-header-info">',
      '      <h4 class="bb-header-title">' + escapeHtml(config.title) + '</h4>',
      '      <span class="bb-header-status"><span class="bb-status-dot"></span> Online</span>',
      '    </div>',
      '  </div>',
      '  <div class="bb-header-actions">',
      '    <button class="bb-header-icon-btn" id="bb-direct-close" aria-label="Close">' + CLOSE_SVG + '</button>',
      '  </div>',
      '</div>',
      '<div class="bb-direct-body">',
      '  <p class="bb-direct-msg">' + escapeHtml(config.message) + '</p>',
      '  <div class="bb-direct-footer">',
      '    <button class="bb-start-chat-btn" id="bb-start-chat-btn">' + WA_SMALL_SVG + ' <span>' + escapeHtml(config.btnText) + '</span></button>',
      '  </div>',
      '</div>',
      getBrandingHtml()
    ].join('');

    var closeBtn = card.querySelector('#bb-direct-close');
    if (closeBtn) closeBtn.addEventListener('click', function (e) { e.stopPropagation(); toggleCard(); });

    var startBtn = card.querySelector('#bb-start-chat-btn');
    if (startBtn) startBtn.addEventListener('click', function (e) { e.stopPropagation(); openWhatsApp(); });
  }

  // Render WebChat / Hybrid Interface
  function renderChatFrame() {
    var avatarUrl = config.botAvatar || '';
    if (avatarUrl && avatarUrl.startsWith('/')) {
      avatarUrl = config.apiUrl.replace(/\/+$/, '') + avatarUrl;
    }

    var avatarHtml = avatarUrl
      ? '<img src="' + escapeHtml(avatarUrl) + '" alt="" onerror="this.style.display=\'none\';if(this.nextElementSibling)this.nextElementSibling.style.display=\'flex\';" /><div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">' + BOT_SVG + '</div>'
      : BOT_SVG;

    var waButtonHtml = (config.showWhatsAppButton && config.phone)
      ? '<button class="bb-header-icon-btn" id="bb-wa-handover" title="Continue on WhatsApp">' + WA_SMALL_SVG + '</button>'
      : '';

    card.innerHTML = [
      '<div class="bb-header">',
      '  <div class="bb-header-left">',
      '    <div class="bb-avatar">' + avatarHtml + '</div>',
      '    <div class="bb-header-info">',
      '      <h4 class="bb-header-title">' + escapeHtml(config.botName) + '</h4>',
      '      <span class="bb-header-status"><span class="bb-status-dot"></span> Online</span>',
      '    </div>',
      '  </div>',
      '  <div class="bb-header-actions">',
      '    <button class="bb-header-icon-btn" id="bb-chat-reset" title="Restart conversation">' + RESTART_SVG + '</button>',
      waButtonHtml,
      '    <button class="bb-header-icon-btn" id="bb-chat-close" title="Close">' + CLOSE_SVG + '</button>',
      '  </div>',
      '</div>',
      '<div class="bb-chat-body" id="bb-chat-body"></div>',
      '<div class="bb-chat-footer">',
      '  <input type="text" class="bb-chat-input" id="bb-chat-input" placeholder="Type a message..." />',
      '  <button class="bb-send-btn" id="bb-chat-send" aria-label="Send">' + SEND_SVG + '</button>',
      '</div>',
      getBrandingHtml()
    ].join('');

    var closeBtn = card.querySelector('#bb-chat-close');
    if (closeBtn) closeBtn.addEventListener('click', function (e) { e.stopPropagation(); toggleCard(); });

    var resetBtn = card.querySelector('#bb-chat-reset');
    if (resetBtn) resetBtn.addEventListener('click', function (e) { e.stopPropagation(); resetWebChat(); });

    var waBtn = card.querySelector('#bb-wa-handover');
    if (waBtn) waBtn.addEventListener('click', function (e) { e.stopPropagation(); openWhatsApp(); });

    var input = card.querySelector('#bb-chat-input');
    var sendBtn = card.querySelector('#bb-chat-send');

    if (input && sendBtn) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          sendUserMessage();
        }
      });
      sendBtn.addEventListener('click', function (e) {
        e.preventDefault();
        sendUserMessage();
      });
    }
  }

  function updateHumanModeState(state) {
    var input = card.querySelector('#bb-chat-input');
    var statusText = card.querySelector('.bb-header-status');
    if (state === 'live' || state === 'live_chat') {
      if (input) input.placeholder = 'Chat with Support...';
      if (statusText) statusText.innerHTML = '<span class="bb-status-dot" style="background:#86efac;box-shadow:0 0 6px #86efac;"></span> Live with Agent';
    } else if (state === 'waiting' || state === true) {
      if (input) input.placeholder = 'Chat with Support...';
      if (statusText) statusText.innerHTML = '<span class="bb-status-dot" style="background:#facc15;box-shadow:0 0 6px #facc15;"></span> Please wait...';
    } else {
      if (input) input.placeholder = 'Type a message...';
      if (statusText) statusText.innerHTML = '<span class="bb-status-dot"></span> Online';
    }
  }

  function appendMessage(msg) {
    var chatBody = card.querySelector('#bb-chat-body');
    if (!chatBody) return;

    if (msg.type === 'system' || msg.type === 'system_notice' || msg.source === 'system') {
      var pill = document.createElement('div');
      pill.className = 'bb-system-pill';

      var rawText = msg.text || msg.content || '';
      var avatarSrc = msg.agentAvatar || msg.senderAvatar || msg.avatar || config.agentAvatar || null;
      var cleanText = rawText.replace(/^[🎧🛎️⏳\s]+/, '');

      var iconHtml = '';
      if (avatarSrc) {
        iconHtml = '<img src="' + escapeHtml(avatarSrc) + '" class="bb-pill-avatar" alt="' + escapeHtml(msg.agentName || 'Agent') + '" />';
      } else {
        var defaultEmoji = rawText.indexOf('⏳') !== -1 ? '⏳' : '🎧';
        iconHtml = '<span class="bb-pill-icon">' + defaultEmoji + '</span>';
      }

      pill.innerHTML = iconHtml + '<span>' + escapeHtml(cleanText || rawText) + '</span>';
      chatBody.appendChild(pill);
      chatBody.scrollTop = chatBody.scrollHeight;
      if (msg.event === 'human_joined' || (msg.text && msg.text.indexOf('joined') !== -1)) {
        updateHumanModeState('live');
      } else if (msg.event === 'bot_resumed' || (msg.text && msg.text.indexOf('ended') !== -1)) {
        updateHumanModeState(false);
      }
      return;
    }

    var row = document.createElement('div');
    row.className = 'bb-msg-row bb-' + (msg.sender === 'user' ? 'user' : 'bot') + (msg.sender === 'agent' ? ' bb-agent' : '');

    var time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    var html = '';

    // Agent Avatar & Name Header on Chat Bubble
    if (msg.sender === 'agent' || msg.isAgent || msg.fromAgent || msg.source === 'live_chat') {
      var agentName = escapeHtml(msg.agentName || msg.senderName || config.agentName || 'Support Agent');
      var avatarSrc = msg.agentAvatar || msg.senderAvatar || msg.avatar || config.agentAvatar || null;
      var avatarHtml = '';
      if (avatarSrc) {
        avatarHtml = '<img src="' + escapeHtml(avatarSrc) + '" class="bb-agent-avatar" alt="' + agentName + '" />';
      } else {
        avatarHtml = '<span class="bb-agent-avatar-icon">🎧</span>';
      }
      html += '<div class="bb-agent-badge">' + avatarHtml + ' <span>' + agentName + '</span></div>';
    }

    if (msg.type === 'image' && msg.mediaUrl) {
      html += '<img class="bb-media-img" src="' + escapeHtml(msg.mediaUrl) + '" alt="media" />';
      if (msg.caption) html += '<div class="bb-bubble-text">' + formatText(msg.caption) + '</div>';
    } else if (msg.type === 'video' && msg.mediaUrl) {
      html += '<video class="bb-media-img" src="' + escapeHtml(msg.mediaUrl) + '" controls></video>';
      if (msg.caption) html += '<div class="bb-bubble-text">' + formatText(msg.caption) + '</div>';
    } else if (msg.type === 'document' && msg.mediaUrl) {
      html += '<a href="' + escapeHtml(msg.mediaUrl) + '" target="_blank" rel="noopener noreferrer" style="display:flex;align-items:center;gap:6px;color:inherit;font-weight:600;text-decoration:none;">📄 ' + escapeHtml(msg.filename || 'Download Document') + '</a>';
    } else {
      html += '<div class="bb-bubble-text">' + formatText(msg.text || '') + '</div>';
    }

    // Option buttons
    if (msg.options && msg.options.length > 0) {
      html += '<div class="bb-options-container">';
      for (var i = 0; i < msg.options.length; i++) {
        var opt = msg.options[i];
        html += '<button class="bb-option-btn" data-opt="' + escapeHtml(opt) + '">' + escapeHtml(opt) + '</button>';
      }
      html += '</div>';
    }

    row.innerHTML = '<div class="bb-bubble">' + html + '</div><span class="bb-msg-time">' + time + '</span>';
    chatBody.appendChild(row);

    // Bind option click handlers
    var optionButtons = row.querySelectorAll('.bb-option-btn');
    for (var j = 0; j < optionButtons.length; j++) {
      optionButtons[j].addEventListener('click', function (e) {
        var optValue = this.getAttribute('data-opt');
        if (optValue) {
          sendOptionSelection(optValue);
        }
      });
    }

    chatBody.scrollTop = chatBody.scrollHeight;
  }

  function showTyping() {
    var chatBody = card.querySelector('#bb-chat-body');
    if (!chatBody) return;
    hideTyping();
    var typing = document.createElement('div');
    typing.id = 'bb-typing-indicator';
    typing.className = 'bb-typing';
    typing.innerHTML = '<span></span><span></span><span></span>';
    chatBody.appendChild(typing);
    chatBody.scrollTop = chatBody.scrollHeight;
  }

  function hideTyping() {
    var existing = card.querySelector('#bb-typing-indicator');
    if (existing && existing.parentNode) {
      existing.parentNode.removeChild(existing);
    }
  }

  // Sequential Message Rendering with Realistic Typing Simulation
  function renderMessageQueue(messages, inputPlaceholder, onComplete) {
    if (!messages || messages.length === 0) {
      hideTyping();
      if (inputPlaceholder) {
        var input = card.querySelector('#bb-chat-input');
        if (input) input.placeholder = inputPlaceholder;
      }
      if (onComplete) onComplete();
      return;
    }

    var idx = 0;
    function processNext() {
      if (idx >= messages.length) {
        hideTyping();
        if (inputPlaceholder) {
          var input = card.querySelector('#bb-chat-input');
          if (input) input.placeholder = inputPlaceholder;
        }
        if (onComplete) onComplete();
        return;
      }

      var currentMsg = messages[idx];
      idx++;

      showTyping();
      var typingDelay = Math.min(Math.max((currentMsg.text || '').length * 20, 600), 1200);

      setTimeout(function () {
        hideTyping();
        chatMessages.push(currentMsg);
        appendMessage(currentMsg);

        if (idx < messages.length) {
          showTyping();
          setTimeout(processNext, 450);
        } else {
          if (inputPlaceholder) {
            var input = card.querySelector('#bb-chat-input');
            if (input) input.placeholder = inputPlaceholder;
          }
          if (onComplete) onComplete();
        }
      }, typingDelay);
    }

    processNext();
  }

  // WebChat API Actions
  function initWebChat() {
    if (!config.sessionId) return;
    showTyping();
    var url = config.apiUrl.replace(/\/+$/, '') + '/api/v1/widget/chat/init/' + encodeURIComponent(config.sessionId);
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ visitorId: visitorId })
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success && data.messages && data.messages.length > 0) {
          renderMessageQueue(data.messages, data.inputPlaceholder, function () {
            if (data.inHumanChat) {
              updateHumanModeState('waiting');
            }
          });
        } else {
          hideTyping();
          if (data.inHumanChat) {
            updateHumanModeState('waiting');
          }
          appendMessage({ sender: 'bot', type: 'text', text: 'Hello! How can we help you today?' });
        }
      })
      .catch(function () {
        hideTyping();
        appendMessage({ sender: 'bot', type: 'text', text: 'Hello! How can we help you today?' });
      });
  }

  function sendUserMessage() {
    var input = card.querySelector('#bb-chat-input');
    if (!input || !input.value.trim() || isFetching) return;
    var text = input.value.trim();
    input.value = '';

    appendMessage({ sender: 'user', type: 'text', text: text });
    submitMessage({ text: text });
  }

  function sendOptionSelection(optionText) {
    if (isFetching) return;
    appendMessage({ sender: 'user', type: 'text', text: optionText });
    submitMessage({ selectedOption: optionText });
  }

  function submitMessage(payload) {
    if (!config.sessionId) return;
    isFetching = true;
    showTyping();

    var url = config.apiUrl.replace(/\/+$/, '') + '/api/v1/widget/chat/message/' + encodeURIComponent(config.sessionId);
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        visitorId: visitorId,
        text: payload.text || '',
        selectedOption: payload.selectedOption || ''
      })
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        isFetching = false;
        if (data.success && data.messages && data.messages.length > 0) {
          renderMessageQueue(data.messages, data.inputPlaceholder, function () {
            if (data.inHumanChat) {
              updateHumanModeState('waiting');
            }
          });
        } else {
          hideTyping();
          if (data.inHumanChat) {
            updateHumanModeState('waiting');
          }
        }
      })
      .catch(function () {
        isFetching = false;
        hideTyping();
      });
  }

  function resetWebChat() {
    var chatBody = card.querySelector('#bb-chat-body');
    if (chatBody) chatBody.innerHTML = '';
    chatMessages = [];
    showTyping();

    var url = config.apiUrl.replace(/\/+$/, '') + '/api/v1/widget/chat/reset/' + encodeURIComponent(config.sessionId);
    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ visitorId: visitorId })
    })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.success && data.messages && data.messages.length > 0) {
          renderMessageQueue(data.messages, data.inputPlaceholder);
        } else {
          hideTyping();
        }
      })
      .catch(function () {
        hideTyping();
      });
  }

  // Load server config dynamically if sessionId provided
  function loadConfigAndRender() {
    if (config.sessionId) {
      var configUrl = config.apiUrl.replace(/\/+$/, '') + '/api/v1/widget/config/' + encodeURIComponent(config.sessionId);
      fetch(configUrl)
        .then(function (res) { return res.json(); })
        .then(function (resData) {
          if (resData.success && resData.config) {
            var c = resData.config;
            config.mode = c.mode || config.mode;
            config.flowId = c.flowId || config.flowId;
            config.botName = c.botName || config.botName;
            config.botAvatar = c.botAvatar || config.botAvatar;
            config.showWhatsAppButton = c.showWhatsAppButton !== undefined ? c.showWhatsAppButton : config.showWhatsAppButton;
            config.hideBranding = c.hideBranding === true || c.hideBranding === 1;
            config.phone = c.phone || config.phone;
            config.title = c.title || config.title;
            config.subtitle = c.subtitle || config.subtitle;
            config.message = c.subtitle || c.title || config.message;
            config.buttonText = c.buttonText || config.buttonText;
            config.triggerText = c.triggerText || config.triggerText;
            config.color = c.color || config.color;
            applyThemeVariables();
          }
          setupUI();
        })
        .catch(function () {
          setupUI();
        });
    } else {
      setupUI();
    }
  }

  function setupUI() {
    if (config.mode === 'webchat' || config.mode === 'hybrid') {
      renderChatFrame();
    } else {
      renderDirectCard();
    }
  }

  btn.addEventListener('click', function (e) {
    e.stopPropagation();
    toggleCard();
  });

  // Real-Time Live Sync & WebSockets
  var ws = null;
  var pollInterval = null;
  var lastMessageTimestamp = new Date().toISOString();

  function connectWebSocket() {
    if (!config.sessionId || typeof WebSocket === 'undefined') return;
    try {
      if (ws && (ws.readyState === 0 || ws.readyState === 1)) return;
      var urlStr = config.apiUrl || window.location.origin;
      var isHttps = urlStr.indexOf('https') === 0 || window.location.protocol === 'https:';
      var wsProto = isHttps ? 'wss:' : 'ws:';
      var host = urlStr.replace(/^https?:\/\//, '').replace(/\/.*$/, '');
      var wsUrl = wsProto + '//' + host + '/api/v1/ws/' + encodeURIComponent(config.sessionId);

      ws = new WebSocket(wsUrl);
      ws.onmessage = function (event) {
        try {
          var payload = JSON.parse(event.data);
          if (payload.dataType === 'widget_message' && payload.data) {
            var data = payload.data;
            if (!data.visitorId || data.visitorId === visitorId) {
              hideTyping();
              if (data.type === 'system_notice' || data.type === 'system') {
                appendMessage({
                  type: 'system_notice',
                  text: data.text || '',
                  agentName: data.agentName,
                  agentAvatar: data.agentAvatar,
                  event: data.event
                });
              } else {
                appendMessage({
                  id: data.id || ('agent_' + Date.now()),
                  sender: 'agent',
                  type: data.type || 'text',
                  text: data.text || '',
                  agentName: data.agentName || config.agentName,
                  agentAvatar: data.agentAvatar || config.agentAvatar,
                  mediaUrl: data.mediaUrl,
                  caption: data.caption,
                  timestamp: data.timestamp || new Date().toISOString()
                });
              }
              lastMessageTimestamp = data.timestamp || new Date().toISOString();
            }
          }
        } catch (e) { }
      };
      ws.onclose = function () { ws = null; };
      ws.onerror = function () { ws = null; };
    } catch (e) {
      ws = null;
    }
  }

  function startLiveSync() {
    connectWebSocket();
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(function () {
      if (ws && ws.readyState === 1) return; // WebSocket is active and handling messages
      if (!config.sessionId || !isOpen) return;

      var pollUrl = config.apiUrl.replace(/\/+$/, '') + '/api/v1/widget/chat/poll/' + encodeURIComponent(config.sessionId) + '?visitorId=' + encodeURIComponent(visitorId) + '&since=' + encodeURIComponent(lastMessageTimestamp);
      fetch(pollUrl)
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.success && data.messages && data.messages.length > 0) {
            hideTyping();
            for (var i = 0; i < data.messages.length; i++) {
              var m = data.messages[i];
              if (m.type === 'system' || m.source === 'system') {
                appendMessage({
                  type: 'system_notice',
                  text: m.text || m.content || '',
                  agentName: m.agentName || data.agentName,
                  agentAvatar: m.agentAvatar || data.agentAvatar,
                  timestamp: m.timestamp
                });
                lastMessageTimestamp = m.timestamp;
              } else if (m.sender === 'agent' || m.fromMe) {
                appendMessage({
                  id: m.id,
                  sender: 'agent',
                  type: m.type || 'text',
                  text: m.text,
                  agentName: m.agentName || data.agentName || config.agentName,
                  agentAvatar: m.agentAvatar || data.agentAvatar || config.agentAvatar,
                  mediaUrl: m.mediaUrl,
                  caption: m.caption,
                  timestamp: m.timestamp
                });
                lastMessageTimestamp = m.timestamp;
              }
            }
          }
        })
        .catch(function () { });
    }, 2500);
  }

  function stopLiveSync() {
    if (pollInterval) {
      clearInterval(pollInterval);
      pollInterval = null;
    }
  }

  // Click outside to close card
  document.addEventListener('click', function (e) {
    if (isOpen && !container.contains(e.target)) {
      isOpen = false;
      card.classList.remove('bb-open');
      container.classList.remove('bb-is-open');
      stopLiveSync();
    }
  });

  // Mount to DOM
  function mount() {
    if (document.body) {
      document.body.appendChild(container);
      loadConfigAndRender();
      trackEvent('impression');

      if (config.autoOpen > 0) {
        setTimeout(function () {
          if (!isOpen) {
            toggleCard();
          }
        }, config.autoOpen);
      }
    } else {
      document.addEventListener('DOMContentLoaded', mount);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }
})();
