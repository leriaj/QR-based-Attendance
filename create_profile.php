<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(
        array('ok' => false, 'message' => 'POST request required.'),
        405
    );
}

$name = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
$email = trim(isset($_POST['email']) ? $_POST['email'] : '');
$token = strtolower(trim(isset($_POST['qr_token']) ? $_POST['qr_token'] : ''));
$pngData = isset($_POST['qr_png']) ? $_POST['qr_png'] : '';
$pdfData = isset($_POST['qr_pdf']) ? $_POST['qr_pdf'] : '';

if (
    $name === '' ||
    strlen($name) > 150 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    !preg_match('/^[a-f0-9]{64}$/', $token)
) {
    json_response(
        array('ok' => false, 'message' => 'Enter a valid name, email, and QR token.'),
        422
    );
}

if (
    !is_string($pngData) ||
    strlen($pngData) > 2000000 ||
    strpos($pngData, 'data:image/png;base64,') !== 0
) {
    json_response(
        array('ok' => false, 'message' => 'QR image could not be generated.'),
        422
    );
}

$png = base64_decode(substr($pngData, strpos($pngData, ',') + 1), true);

if ($png === false || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
    json_response(
        array('ok' => false, 'message' => 'Invalid QR image.'),
        422
    );
}

$pdfPrefix = 'data:application/pdf;base64,';

if (
    !is_string($pdfData) ||
    strlen($pdfData) > 2000000 ||
    strpos($pdfData, $pdfPrefix) !== 0
) {
    json_response(
        array('ok' => false, 'message' => 'PDF attendance pass could not be generated.'),
        422
    );
}

$pdf = base64_decode(substr($pdfData, strlen($pdfPrefix)), true);

if ($pdf === false || substr($pdf, 0, 5) !== '%PDF-') {
    json_response(
        array('ok' => false, 'message' => 'Invalid PDF attendance pass.'),
        422
    );
}

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        'INSERT INTO profiles (full_name, email, qr_token) VALUES (?, ?, ?)'
    );
    $stmt->execute(array($name, $email, $token));

    $id = (int) $pdo->lastInsertId();

    $dir = __DIR__ . '/uploads/qr';

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create QR folder.');
    }

    $relative = 'uploads/qr/profile_' . $id . '.png';
    $absolute = __DIR__ . '/' . $relative;
    $pdfAbsolute = __DIR__ . '/uploads/qr/profile_' . $id . '_pass.pdf';

    if (file_put_contents($absolute, $png) === false) {
        throw new RuntimeException('Cannot save QR image.');
    }

    if (file_put_contents($pdfAbsolute, $pdf) === false) {
        throw new RuntimeException('Cannot save QR pass PDF.');
    }

    $stmt = $pdo->prepare(
        'UPDATE profiles SET qr_image = ? WHERE id = ?'
    );
    $stmt->execute(array($relative, $id));

    $mail = send_qr_email($email, $name, create_download_url($id, $token));

    json_response(array(
        'ok' => true,
        'profile_id' => $id,
        'email_sent' => $mail['sent'],
        'message' => $mail['sent']
            ? 'Profile created. QR code was emailed successfully.'
            : 'Profile and QR code saved, but email was not sent: ' . $mail['message']
    ));

} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {
        json_response(
            array('ok' => false, 'message' => 'That email or QR token is already registered.'),
            409
        );
    }

    error_log('Profile database error: ' . $ex->getMessage());

    json_response(
        array('ok' => false, 'message' => 'Database error. Check your database settings and PHP error log.'),
        500
    );

} catch (Throwable $ex) {
    error_log('Profile creation error: ' . $ex->getMessage());

    json_response(
        array('ok' => false, 'message' => 'Could not finish saving the profile. Check folder permissions and the PHP error log.'),
        500
    );
}

function send_qr_email($email, $name, $downloadUrl)
{
    if (
        !defined('MAIL_FROM') || MAIL_FROM === '' ||
        !defined('MAIL_USERNAME') || MAIL_USERNAME === '' ||
        !defined('MAIL_PASSWORD') || MAIL_PASSWORD === ''
    ) {
        return array(
            'sent' => false,
            'message' => 'Configure MAIL_FROM, MAIL_USERNAME and MAIL_PASSWORD in config.php.'
        );
    }

    if (!extension_loaded('openssl')) {
        return array(
            'sent' => false,
            'message' => 'Enable extension=openssl in php.ini, then restart Apache.'
        );
    }

    try {
        $alternativeBoundary = 'qr-alternative-' . bin2hex(random_bytes(16));
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeDownloadUrl = htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8');
        $text =
            "Hello $name,\r\n\r\n" .
            "Your attendance pass is ready. Open the HTML version of this email and use the download button to get your PDF.\r\n\r\n" .
            "Present the QR code when checking in.\r\n\r\n" .
            "Regards,\r\n" .
            "QR Attendance";
        $html =
            '<!doctype html><html><body style="margin:0;padding:32px 14px;background:#f0f4f9;font-family:Arial,Helvetica,sans-serif;color:#1e2e42">' .
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f0f4f9"><tr><td align="center">' .
            '<table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background:#fff;border:1px solid #dbe4ef">' .
            '<tr><td style="padding:19px 28px;background:#173957;color:#fff">' .
            '<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>' .
            '<td width="34" height="34" align="center" style="background:#b9ddff;color:#173957;font-size:11px;font-weight:bold">QR</td>' .
            '<td style="padding-left:11px;color:#fff;font-size:12px;font-weight:bold;letter-spacing:1px">QR ATTENDANCE</td>' .
            '</tr></table></td></tr>' .
            '<tr><td style="padding:30px 32px 32px">' .
            '<p style="margin:0 0 9px;color:#3478bd;font-size:10px;font-weight:bold;letter-spacing:1px">PROFILE CREATED</p>' .
            '<h1 style="margin:0 0 22px;color:#173957;font-size:25px;line-height:1.3;font-weight:600">Your attendance pass is ready</h1>' .
            '<p style="margin:0 0 12px;font-size:14px;line-height:1.6">Hello ' . $safeName . ',</p>' .
            '<p style="margin:0;color:#53647a;font-size:14px;line-height:1.7">Your profile is active. Download your personal pass using the button below, then present its QR code when you check in.</p>' .
            '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:25px 0 0"><tr><td bgcolor="#3478bd" align="center" style="border-radius:4px">' .
            '<a href="' . $safeDownloadUrl . '" style="display:inline-block;padding:14px 22px;color:#fff;font-size:14px;font-weight:bold;text-decoration:none">Download attendance pass</a>' .
            '</td></tr></table>' .
            '<p style="margin:25px 0 0;color:#53647a;font-size:14px;line-height:1.7">Regards,<br><strong style="color:#1e2e42">QR Attendance</strong></p>' .
            '</td></tr>' .
            '<tr><td style="padding:15px 32px;border-top:1px solid #dbe4ef;color:#7a8799;font-size:11px;line-height:1.5">Automated message about your QR Attendance profile.</td></tr>' .
            '</table></td></tr></table></body></html>';

        $body =
            "This is a multi-part message in MIME format.\r\n\r\n" .
            "--$alternativeBoundary\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($text)) . "\r\n" .
            "--$alternativeBoundary\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($html)) . "\r\n" .
            "--$alternativeBoundary--\r\n";

        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'QR Attendance';
        $domain = substr(strrchr(MAIL_FROM, '@'), 1);

        $headers =
            'Date: ' . date('r') . "\r\n" .
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . MAIL_FROM . ">\r\n" .
            'To: <' . $email . ">\r\n" .
            'Subject: =?UTF-8?B?' . base64_encode('Your QR Attendance PDF') . "?=\r\n" .
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . ">\r\n" .
            "MIME-Version: 1.0\r\n" .
            'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . '"';

        smtp_send($email, $headers . "\r\n\r\n" . $body);

        return array('sent' => true, 'message' => 'Email sent successfully.');

    } catch (Throwable $ex) {
        error_log('Email sending error: ' . $ex->getMessage());

        return array('sent' => false, 'message' => $ex->getMessage());
    }
}

function create_download_url($id, $token)
{
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';

    if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i', $host)) {
        return '';
    }

    $https = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' && $_SERVER['HTTPS'] !== '';
    $basePath = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
    $basePath = rtrim($basePath, '/.');

    return ($https ? 'https://' : 'http://') . $host . $basePath .
        '/download_qr.php?id=' . (int) $id . '&token=' . rawurlencode($token);
}

function smtp_read($fp)
{
    $data = '';

    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;

        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }

    return $data;
}

function smtp_expect($fp, $codes, $command = null)
{
    if ($command !== null) {
        fwrite($fp, $command . "\r\n");
    }

    $response = smtp_read($fp);

    if ($response === '') {
        throw new RuntimeException('No response from the mail server.');
    }

    if (!in_array((int) substr($response, 0, 3), (array) $codes, true)) {
        throw new RuntimeException('Mail server said: ' . trim($response));
    }

    return $response;
}

function smtp_send($to, $message)
{
    $verify = defined('MAIL_SSL_VERIFY') ? (bool) MAIL_SSL_VERIFY : false;

    $context = stream_context_create(array(
        'ssl' => array(
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify
        )
    ));

    $fp = @stream_socket_client(
        'ssl://smtp.gmail.com:465',
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$fp) {
        throw new RuntimeException('Cannot connect to Gmail: ' . $errstr . ' (' . $errno . ')');
    }

    stream_set_timeout($fp, 20);

    try {
        $password = str_replace(' ', '', MAIL_PASSWORD);

        smtp_expect($fp, 220);
        smtp_expect($fp, 250, 'EHLO localhost');
        smtp_expect($fp, 334, 'AUTH LOGIN');
        smtp_expect($fp, 334, base64_encode(MAIL_USERNAME));
        smtp_expect($fp, 235, base64_encode($password));
        smtp_expect($fp, 250, 'MAIL FROM:<' . MAIL_FROM . '>');
        smtp_expect($fp, array(250, 251), 'RCPT TO:<' . $to . '>');
        smtp_expect($fp, 354, 'DATA');

        $message = str_replace(array("\r\n", "\r"), "\n", $message);
        $message = str_replace("\n", "\r\n", $message);
        $message = preg_replace('/^\./m', '..', $message);

        fwrite($fp, $message . "\r\n.\r\n");
        smtp_expect($fp, 250);

        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}