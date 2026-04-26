<?php
/**
 * Loaded inside iframe after consultation save or error — notifies parent window (ehr_module modal).
 */
header('Cache-Control: no-store');
$error = isset($_GET['error']) ? preg_replace('/[^a-z0-9_]/i', '', (string) $_GET['error']) : '';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $error !== '' ? 'Notice' : 'Saved' ?></title>
</head>
<body style="margin:0;font-family:system-ui,-apple-system,sans-serif;text-align:center;padding:2rem;<?= $error !== '' ? 'background:#fef2f2;color:#991b1b;' : 'background:#f0fdf4;color:#065f46;' ?>">
    <?php if ($error !== ''): ?>
        <p style="margin:0;font-weight:600;">This action could not be completed.</p>
        <p style="margin:8px 0 0;font-size:13px;">Closing…</p>
    <?php else: ?>
        <p style="margin:0;font-weight:600;">Consultation saved.</p>
        <p style="margin:8px 0 0;font-size:14px;color:#047857;">Closing…</p>
    <?php endif; ?>
    <script>
        (function () {
            try {
                if (window.parent && window.parent !== window) {
                    <?php if ($error !== ''): ?>
                    window.parent.postMessage({ type: 'hb-ehr-consultation-error', code: <?= json_encode($error, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?> }, window.location.origin);
                    <?php else: ?>
                    window.parent.postMessage({ type: 'hb-ehr-consultation-saved' }, window.location.origin);
                    <?php endif; ?>
                }
            } catch (e) {}
        })();
    </script>
</body>
</html>
