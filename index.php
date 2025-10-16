<?php

require "configure.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If already verified, go straight to index
if (isset($_SESSION['recaptcha_ok']) && $_SESSION['recaptcha_ok'] === true) {
    header('Location: index.php');
    exit();
}

$error_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recaptcha_response = isset($_POST['g-recaptcha-response']) ? trim($_POST['g-recaptcha-response']) : '';

    if (empty($recaptcha_response)) {
        $error_message = 'Veuillez cocher la case reCAPTCHA.';
    } else {
        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $post_fields = http_build_query([
            'secret' => $recaptcha_secret,
            'response' => $recaptcha_response,
            'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
        ]);

        $verification_success = false;

        if (function_exists('curl_init')) {
            $ch = curl_init($verify_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $result = curl_exec($ch);
            if ($result !== false) {
                $decoded = json_decode($result, true);
                $verification_success = isset($decoded['success']) && $decoded['success'] === true;
            }
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => $post_fields,
                    'timeout' => 10,
                ]
            ]);
            $result = @file_get_contents($verify_url, false, $context);
            if ($result !== false) {
                $decoded = json_decode($result, true);
                $verification_success = isset($decoded['success']) && $decoded['success'] === true;
            }
        }

        if ($verification_success) {
            $_SESSION['recaptcha_ok'] = true;
            header('Location: index.php');
            exit();
        } else {
            $error_message = 'Vérification reCAPTCHA échouée. Veuillez réessayer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification reCAPTCHA</title>
    <style>
        html, body { height: 100%; }
        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .title { margin: 0 0 12px; font-size: 18px; color: #111827; }
        .subtitle { margin: 0 0 20px; font-size: 14px; color: #6b7280; }
        .error { margin: 0 0 12px; color: #b91c1c; font-size: 14px; }
        .submit { margin-top: 16px; padding: 10px 14px; border-radius: 8px; border: 1px solid #d1d5db; background: #111827; color: #fff; cursor: pointer; }
    </style>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script>
        function onSubmitToken() {
            document.getElementById('recaptcha-form').submit();
        }
    </script>
    <noscript>
        <style>.card{display:none}</style>
        JavaScript requis pour reCAPTCHA.
    </noscript>
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <!-- The CSP meta above mitigates mixed-content issues when served over HTTP in local setups. -->
    <!-- Ensure this file remains minimal to avoid interfering with main page styles. -->
</head>
<body>
    <div class="card">
        <h1 class="title">Vérification requise</h1>
        <p class="subtitle">Merci de confirmer que vous n'êtes pas un robot.</p>
        <?php if (!empty($error_message)): ?>
            <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form id="recaptcha-form" method="post">
            <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($recaptcha_site); ?>"></div>
            <button type="submit" class="submit">Continuer</button>
        </form>
    </div>
</body>
</html>


