<div id="appSessionTimeout" class="app-session-timeout" hidden aria-hidden="true">
    <section
        class="app-session-timeout-dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="appSessionTimeoutTitle"
        aria-describedby="appSessionTimeoutDescription"
        tabindex="-1"
    >
        <span class="app-session-timeout-kicker">Seguridad de la sesión</span>
        <h2 id="appSessionTimeoutTitle">Tu sesión está por expirar</h2>
        <p id="appSessionTimeoutDescription">
            La sesión se cerrará por inactividad en
            <strong id="appSessionTimeoutCountdown">10:00</strong>.
        </p>
        <p id="appSessionTimeoutStatus" class="app-session-timeout-status" role="status" hidden></p>
        <div class="app-session-timeout-actions">
            <button type="button" id="appSessionTimeoutContinue" class="app-session-timeout-primary">Continuar sesión</button>
            <button type="button" id="appSessionTimeoutLogin" class="app-session-timeout-secondary">Ir al login</button>
        </div>
    </section>
</div>
