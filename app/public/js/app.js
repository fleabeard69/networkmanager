'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── Delete / dangerous action confirmations ───────────────────────────
    // Any submit button with data-confirm will prompt before the form submits.
    document.querySelectorAll('button[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!window.confirm(btn.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // ── Collapsible toggle sections ───────────────────────────────────────
    // Toggles visibility of an element by ID.
    // data-toggle="element-id"
    // data-show-text="+ Add ..."   (label when section is hidden)
    // data-hide-text="− Cancel"    (label when section is visible)
    document.querySelectorAll('[data-toggle]').forEach(btn => {
        const targetId = btn.dataset.toggle;
        const target   = document.getElementById(targetId);
        if (!target) return;

        const showText = btn.dataset.showText || btn.textContent;
        const hideText = btn.dataset.hideText || 'Cancel';

        btn.addEventListener('click', () => {
            const isHidden = target.classList.contains('hidden');
            target.classList.toggle('hidden', !isHidden);
            btn.textContent = isHidden ? hideText : showText;

            // If opening, focus the first input inside
            if (isHidden) {
                const first = target.querySelector('input, select, textarea');
                if (first) first.focus();
            }
        });
    });

    // ── Port card navigation ──────────────────────────────────────────────
    // Clicking a port card navigates to its edit page.
    // data-href="/ports/{id}/edit"
    document.querySelectorAll('.port-card[data-href]').forEach(card => {
        card.addEventListener('click', () => {
            window.location.href = card.dataset.href;
        });

        // Keyboard accessibility: treat Enter/Space as a click
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'button');
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                window.location.href = card.dataset.href;
            }
        });
    });

    // ── Auto-dismiss flash messages ───────────────────────────────────────
    document.querySelectorAll('.flash').forEach(flash => {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.4s ease';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 400);
        }, 5000);
    });

});
