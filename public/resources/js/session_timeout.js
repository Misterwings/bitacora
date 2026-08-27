(function () {
    'use strict';

    var body = document.body;
    var root = document.getElementById('appSessionTimeout');
    if (!body || !root) return;

    function numberValue(value, fallback) {
        var parsed = Number(value);
        return isFinite(parsed) && parsed >= 0 ? parsed : fallback;
    }

    var idleTimeout = numberValue(body.getAttribute('data-session-idle-timeout'), 0);
    var maxLifetime = numberValue(body.getAttribute('data-session-max-lifetime'), 0);
    var warningSeconds = numberValue(body.getAttribute('data-session-warning-seconds'), 600);
    var serverTime = numberValue(body.getAttribute('data-session-server-time'), 0);
    var fallbackNow = Math.floor(Date.now() / 1000);
    var lastActivityAt = numberValue(body.getAttribute('data-session-last-activity'), serverTime || fallbackNow);
    var createdAt = numberValue(body.getAttribute('data-session-created-at'), serverTime || fallbackNow);
    var statusUrl = body.getAttribute('data-session-status-url') || '../bd/session.php';
    var loginUrl = body.getAttribute('data-session-login-url') || '../index.php';
    var csrfToken = body.getAttribute('data-session-csrf') || '';

    if (!idleTimeout && !maxLifetime) return;

    var dialog = root.querySelector('.app-session-timeout-dialog');
    var countdown = document.getElementById('appSessionTimeoutCountdown');
    var status = document.getElementById('appSessionTimeoutStatus');
    var continueButton = document.getElementById('appSessionTimeoutContinue');
    var loginButton = document.getElementById('appSessionTimeoutLogin');
    if (!dialog || !countdown || !continueButton || !loginButton) return;

    var clockOffsetMs = serverTime > 0 ? (serverTime * 1000) - Date.now() : 0;
    var warningTimer = null;
    var countdownTimer = null;
    var statusRequest = null;
    var lastFocusedElement = null;
    var titleBeforeAttention = null;
    var warningVisible = false;
    var refreshInProgress = false;
    var redirecting = false;

    function serverNow() {
        return (Date.now() + clockOffsetMs) / 1000;
    }

    function updateServerClock(value) {
        var parsed = numberValue(value, 0);
        if (parsed > 0) {
            clockOffsetMs = (parsed * 1000) - Date.now();
        }
    }

    function sessionDeadline() {
        var deadlines = [];
        if (idleTimeout > 0 && lastActivityAt > 0) {
            deadlines.push(lastActivityAt + idleTimeout);
        }
        if (maxLifetime > 0 && createdAt > 0) {
            deadlines.push(createdAt + maxLifetime);
        }
        return deadlines.length ? Math.min.apply(Math, deadlines) : null;
    }

    function remainingSeconds() {
        var deadline = sessionDeadline();
        return deadline === null ? null : Math.max(0, Math.ceil(deadline - serverNow()));
    }

    function formatRemaining(seconds) {
        var minutes = Math.floor(seconds / 60);
        var remainder = seconds % 60;
        return String(minutes) + ':' + (remainder < 10 ? '0' : '') + String(remainder);
    }

    function setStatus(message) {
        if (!status) return;
        status.textContent = message || '';
        status.hidden = !message;
    }

    function focusDialog() {
        if (redirecting || root.hidden) return;
        try {
            window.focus();
        } catch (error) {
            // Browsers can reject focusing a background window.
        }
        dialog.focus();
    }

    function setAttentionTitle(active) {
        if (active) {
            if (titleBeforeAttention === null) {
                titleBeforeAttention = document.title;
                document.title = 'Sesión por expirar | ' + titleBeforeAttention;
            }
            return;
        }

        if (titleBeforeAttention !== null) {
            document.title = titleBeforeAttention;
            titleBeforeAttention = null;
        }
    }

    function showWarning() {
        if (redirecting) return;

        if (!warningVisible) {
            lastFocusedElement = document.activeElement;
            warningVisible = true;
            root.hidden = false;
            root.setAttribute('aria-hidden', 'false');
            body.classList.add('app-session-timeout-open');
            setAttentionTitle(true);
        }

        focusDialog();
        window.setTimeout(focusDialog, 0);
    }

    function hideWarning() {
        if (!warningVisible) return;

        warningVisible = false;
        if (countdownTimer !== null) {
            window.clearTimeout(countdownTimer);
            countdownTimer = null;
        }
        root.hidden = true;
        root.setAttribute('aria-hidden', 'true');
        body.classList.remove('app-session-timeout-open');
        setAttentionTitle(false);
        setStatus('');

        if (lastFocusedElement && document.documentElement.contains(lastFocusedElement)) {
            lastFocusedElement.focus();
        }
        lastFocusedElement = null;
    }

    function clearTimers() {
        if (warningTimer !== null) {
            window.clearTimeout(warningTimer);
            warningTimer = null;
        }
        if (countdownTimer !== null) {
            window.clearTimeout(countdownTimer);
            countdownTimer = null;
        }
    }

    function redirectToLogin() {
        if (redirecting) return;

        redirecting = true;
        window.appSessionTimeoutRedirecting = true;
        clearTimers();
        setAttentionTitle(false);
        document.title = 'Sesión cerrada';
        setStatus('La sesión terminó. Redirigiendo al login...');

        try {
            window.location.replace(loginUrl);
        } catch (error) {
            window.location.href = loginUrl;
        }
    }

    function updateCountdown() {
        if (!warningVisible || redirecting) return;

        if (countdownTimer !== null) {
            window.clearTimeout(countdownTimer);
            countdownTimer = null;
        }

        var remaining = remainingSeconds();
        if (remaining === null) {
            hideWarning();
            return;
        }
        if (remaining <= 0) {
            verifyExpiration();
            return;
        }

        countdown.textContent = formatRemaining(remaining);
        countdownTimer = window.setTimeout(updateCountdown, 1000);
    }

    function applySessionPayload(payload) {
        if (!payload || payload.authenticated !== true) return false;

        updateServerClock(payload.server_time);
        if (payload.idle_timeout_seconds !== undefined) {
            idleTimeout = numberValue(payload.idle_timeout_seconds, idleTimeout);
        }
        if (payload.max_lifetime_seconds !== undefined) {
            maxLifetime = numberValue(payload.max_lifetime_seconds, maxLifetime);
        }
        if (payload.last_activity_at !== undefined) {
            lastActivityAt = numberValue(payload.last_activity_at, lastActivityAt);
        }
        if (payload.session_created_at !== undefined) {
            createdAt = numberValue(payload.session_created_at, createdAt);
        }
        return true;
    }

    function sessionError(message, expired) {
        var error = new Error(message || 'No fue posible consultar la sesión.');
        error.sessionExpired = Boolean(expired);
        return error;
    }

    function headerValue(headers, name) {
        if (!headers || typeof headers.get !== 'function') return '';
        return headers.get(name) || '';
    }

    function syncFromResponse(response) {
        if (!response || !response.headers) return;

        if (headerValue(response.headers, 'X-App-Session-Expired') === '1') {
            redirectToLogin();
            return;
        }

        var lastActivity = numberValue(headerValue(response.headers, 'X-App-Session-Last-Activity'), 0);
        if (lastActivity <= 0) return;

        updateServerClock(headerValue(response.headers, 'X-App-Session-Server-Time'));
        lastActivityAt = lastActivity;

        var remaining = remainingSeconds();
        if (warningVisible && (remaining === null || remaining > warningSeconds)) {
            hideWarning();
        }
        scheduleWarningCheck();
    }

    function syncFromXhr(xhr) {
        if (!xhr || typeof xhr.getResponseHeader !== 'function') return;

        if (xhr.getResponseHeader('X-App-Session-Expired') === '1') {
            redirectToLogin();
            return;
        }

        var lastActivity = numberValue(xhr.getResponseHeader('X-App-Session-Last-Activity'), 0);
        if (lastActivity <= 0) return;

        updateServerClock(xhr.getResponseHeader('X-App-Session-Server-Time'));
        lastActivityAt = lastActivity;

        var remaining = remainingSeconds();
        if (warningVisible && (remaining === null || remaining > warningSeconds)) {
            hideWarning();
        }
        scheduleWarningCheck();
    }

    function parseSessionResponse(response) {
        syncFromResponse(response);

        if (!response.ok) {
            throw sessionError('La sesión ya no está disponible.', response.status === 401 || response.status === 403);
        }

        return response.json().then(function (payload) {
            if (!payload || payload.authenticated !== true || payload.ok !== true) {
                throw sessionError('La sesión ya no está disponible.', true);
            }
            return payload;
        });
    }

    function requestStatus() {
        if (statusRequest) return statusRequest;

        statusRequest = window.fetch(statusUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        }).then(parseSessionResponse);

        statusRequest.then(function () {
            statusRequest = null;
        }, function () {
            statusRequest = null;
        });

        return statusRequest;
    }

    function refreshSession() {
        return window.fetch(statusUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-Token': csrfToken
            },
            body: ''
        }).then(parseSessionResponse);
    }

    function verifyExpiration() {
        if (redirecting) return;

        requestStatus().then(function (payload) {
            if (!applySessionPayload(payload)) {
                redirectToLogin();
                return;
            }
            var remaining = remainingSeconds();
            if (remaining !== null && remaining <= 0) {
                redirectToLogin();
                return;
            }
            if (remaining === null) {
                hideWarning();
                scheduleWarningCheck();
                return;
            }
            checkWarningWindow();
        }).catch(function () {
            redirectToLogin();
        });
    }

    function scheduleWarningCheck() {
        if (redirecting) return;
        if (warningTimer !== null) {
            window.clearTimeout(warningTimer);
            warningTimer = null;
        }

        var deadline = sessionDeadline();
        if (deadline === null) {
            hideWarning();
            return;
        }

        var delay = (deadline - warningSeconds) - serverNow();
        if (delay <= 0) {
            warningTimer = window.setTimeout(checkWarningWindow, 0);
            return;
        }

        warningTimer = window.setTimeout(checkWarningWindow, Math.min(delay * 1000, 2147483647));
    }

    function checkWarningWindow() {
        if (redirecting) return;
        warningTimer = null;

        var remaining = remainingSeconds();
        if (remaining === null) {
            hideWarning();
            return;
        }
        if (remaining <= 0) {
            verifyExpiration();
            return;
        }
        if (remaining > warningSeconds) {
            scheduleWarningCheck();
            return;
        }

        requestStatus().then(function (payload) {
            if (!applySessionPayload(payload)) {
                redirectToLogin();
                return;
            }

            var currentRemaining = remainingSeconds();
            if (currentRemaining !== null && currentRemaining <= 0) {
                verifyExpiration();
            } else if (currentRemaining === null) {
                hideWarning();
                scheduleWarningCheck();
            } else if (currentRemaining <= warningSeconds) {
                showWarning();
                updateCountdown();
            } else {
                hideWarning();
                scheduleWarningCheck();
            }
        }).catch(function (error) {
            if (error && error.sessionExpired) {
                redirectToLogin();
                return;
            }

            showWarning();
            setStatus('No se pudo verificar la sesión. Intenta continuar para renovarla.');
            updateCountdown();
        });
    }

    continueButton.addEventListener('click', function () {
        if (refreshInProgress || redirecting) return;

        refreshInProgress = true;
        continueButton.disabled = true;
        setStatus('Renovando la sesión...');

        refreshSession().then(function (payload) {
            refreshInProgress = false;
            continueButton.disabled = false;
            if (!applySessionPayload(payload)) {
                redirectToLogin();
                return;
            }
            hideWarning();
            scheduleWarningCheck();
        }).catch(function (error) {
            refreshInProgress = false;
            continueButton.disabled = false;
            if (error && error.sessionExpired) {
                redirectToLogin();
                return;
            }
            setStatus('No se pudo renovar la sesión. Revisa tu conexión e intenta nuevamente.');
            updateCountdown();
        });
    });

    loginButton.addEventListener('click', redirectToLogin);

    dialog.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            return;
        }
        if (event.key !== 'Tab') return;

        var focusable = [continueButton, loginButton].filter(function (element) {
            return !element.disabled && !element.hidden;
        });
        if (!focusable.length) return;

        var currentIndex = focusable.indexOf(document.activeElement);
        if (event.shiftKey && (currentIndex <= 0 || currentIndex === -1)) {
            event.preventDefault();
            focusable[focusable.length - 1].focus();
        } else if (!event.shiftKey && (currentIndex === focusable.length - 1 || currentIndex === -1)) {
            event.preventDefault();
            focusable[0].focus();
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            if (warningVisible) focusDialog();
            checkWarningWindow();
        }
    });
    window.addEventListener('focus', checkWarningWindow);
    window.addEventListener('pageshow', checkWarningWindow);

    window.appSessionTimeout = {
        syncFromResponse: syncFromResponse,
        syncFromXhr: syncFromXhr,
        redirect: redirectToLogin
    };

    if (typeof window.fetch === 'function') {
        var nativeFetch = window.fetch;
        window.fetch = function () {
            return nativeFetch.apply(this, arguments).then(function (response) {
                syncFromResponse(response);
                return response;
            });
        };
    }

    if (window.jQuery) {
        window.jQuery(document).on('ajaxComplete.appSessionTimeout', function (event, xhr) {
            syncFromXhr(xhr);
        });
    }

    checkWarningWindow();
})();
