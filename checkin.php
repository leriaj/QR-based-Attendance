<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(array('ok' => false, 'message' => 'POST request required.'), 405);
}

$value = trim(isset($_POST['qr_value']) ? $_POST['qr_value'] : '');
$token = $value;

if (filter_var($value, FILTER_VALIDATE_URL)) {
    $parts = parse_url($value);
    $query = array();

    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
    }

    $token = isset($query['token']) ? $query['token'] : '';
}

$systemMarker = 'QRATTENDANCE:V1:';

if (strpos($token, $systemMarker) === 0) {
    $token = substr($token, strlen($systemMarker));
}

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    json_response(array('ok' => false, 'message' => 'Invalid QR code.'), 422);
}

$stmt = db()->prepare('SELECT id, full_name, email FROM profiles WHERE qr_token = ? LIMIT 1');
$stmt->execute(array($token));
$profile = $stmt->fetch();

if (!$profile) {
    json_response(array('ok' => false, 'message' => 'Profile not found.'), 404);
}

try {
    $stmt = db()->prepare(
        'SELECT id, time_out FROM attendance WHERE profile_id = ? AND attendance_date = CURDATE() LIMIT 1'
    );
    $stmt->execute(array((int) $profile['id']));
    $today = $stmt->fetch();

    if ($today) {
        if ($today['time_out'] !== null) {
            json_response(array('ok' => false, 'message' => 'This profile has already checked in and out today.'), 409);
        }

        $stmt = db()->prepare(
            'UPDATE attendance SET time_out = NOW() WHERE id = ? AND time_out IS NULL'
        );
        $stmt->execute(array((int) $today['id']));

        if ($stmt->rowCount() !== 1) {
            json_response(array('ok' => false, 'message' => 'This profile has already checked out today.'), 409);
        }

        $stmt = db()->prepare('SELECT time_out FROM attendance WHERE id = ?');
        $stmt->execute(array((int) $today['id']));
        $timeOut = $stmt->fetchColumn();
        $emailSent = send_attendance_email($profile, 'check_out', $timeOut);

        json_response(array(
            'ok' => true,
            'action' => 'check_out',
            'message' => 'Check-out recorded for ' . $profile['full_name'] . '.',
            'time_out' => $timeOut,
            'email_sent' => $emailSent
        ));
    }

    $stmt = db()->prepare(
        'INSERT INTO attendance (profile_id, attendance_date, time_in) VALUES (?, CURDATE(), NOW())'
    );
    $stmt->execute(array((int) $profile['id']));
    $timeIn = date('Y-m-d H:i:s');
    $emailSent = send_attendance_email($profile, 'check_in', $timeIn);

    json_response(array(
        'ok' => true,
        'action' => 'check_in',
        'message' => 'Check-in recorded for ' . $profile['full_name'] . '.',
        'time_in' => $timeIn,
        'email_sent' => $emailSent
    ));
} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {
        json_response(array('ok' => false, 'message' => $profile['full_name'] . ' has already checked in today.'), 409);
    }

    error_log($ex->getMessage());
    json_response(array('ok' => false, 'message' => 'Could not save attendance.'), 500);
}

function send_attendance_email($profile, $action, $timestamp)
{
    if (!defined('MAIL_FROM') || MAIL_FROM === '') {
        return false;
    }

    $isCheckIn = $action === 'check_in';
    $actionLabel = $isCheckIn ? 'Check-in' : 'Check-out';
    $timeLabel = $isCheckIn ? 'Time in' : 'Time out';
    $safeName = e($profile['full_name']);
    $safeTime = e($timestamp);
    $emailBody =
        '<!doctype html><html><body style="margin:0;padding:28px 12px;background:#f0f4f9;font-family:Arial,Helvetica,sans-serif;color:#1e2e42">' .
        '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center">' .
        '<table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:560px;background:#fff;border:1px solid #dbe4ef">' .
        '<tr><td style="padding:24px 28px;background:#173957;color:#fff"><p style="margin:0 0 18px;color:#b9ddff;font-size:12px;font-weight:bold;letter-spacing:1.5px">QR ATTENDANCE</p><h1 style="margin:0;font-size:25px">' . $actionLabel . ' confirmed</h1></td></tr>' .
        '<tr><td style="padding:26px 28px"><p style="margin:0 0 12px;font-size:15px">Hello ' . $safeName . ',</p><p style="margin:0;color:#53647a;font-size:14px;line-height:1.6">Your attendance was successfully recorded.</p><p style="margin:20px 0 0;padding:14px;background:#f0f4f9;border-left:4px solid #3478bd;font-size:14px"><strong>' . $timeLabel . ':</strong> ' . $safeTime . '</p></td></tr>' .
        '<tr><td style="padding:16px 28px;border-top:1px solid #dbe4ef;color:#69788c;font-size:11px">QR Attendance</td></tr>' .
        '</table></td></tr></table></body></html>';
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'QR Attendance';
    $headers = array(
        'MIME-Version: 1.0',
        'From: ' . $fromName . ' <' . MAIL_FROM . '>',
        'Content-Type: text/html; charset=UTF-8'
    );

    try {
        $subject = 'Attendance ' . strtolower($actionLabel) . ' confirmed';
        $sent = @mail($profile['email'], $subject, $emailBody, implode("\r\n", $headers));
    } catch (Throwable $emailError) {
        error_log(ucfirst($action) . ' email error: ' . $emailError->getMessage());
        return false;
    }

    if (!$sent) {
        error_log(ucfirst($action) . ' confirmation email could not be sent to profile ' . (int) $profile['id']);
    }

    return $sent;
}
