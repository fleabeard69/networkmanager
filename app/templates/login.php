<?php $title = 'Sign In'; ?>
<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <span class="brand-dot brand-dot-lg"></span>
            <h1 class="login-title">NetManager</h1>
            <p class="login-subtitle">UDM Pro Port Manager</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="flash flash-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" action="/login" class="login-form">
            <?= Csrf::field() ?>

            <div class="field-group">
                <label class="field-label" for="username">Username</label>
                <input
                    class="field-input"
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="field-group">
                <label class="field-label" for="password">Password</label>
                <input
                    class="field-input"
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary btn-full">Sign In</button>
        </form>
    </div>
</div>
