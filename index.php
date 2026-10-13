<?php
require __DIR__ . '/config.php';

$profiles = db()
    ->query('SELECT id, full_name, email, qr_image, created_at FROM profiles ORDER BY id DESC')
    ->fetchAll();

$todayCount = (int) db()
    ->query('SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()')
    ->fetchColumn();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>QR Attendance</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="design.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="index.php">
                <span class="logo">QR</span>
                <span>QR Attendance</span>
            </a>
            <nav aria-label="Main navigation">
                <a href="index.php" aria-current="page">Profiles</a>
                <a href="scan.php">Scan</a>
                <a href="attendance.php">Records</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">DAILY ATTENDANCE</p>
                <h1>Presence,<br>accounted for.</h1>
                <p>People and activity, in one place.</p>
            </div>
            <div class="stat">
                <span>Check-ins today</span>
                <strong><?= $todayCount ?></strong>
            </div>
        </section>

        <div class="layout">
            <section class="panel profile-form-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">NEW ENTRY</p>
                        <h2>Add a profile</h2>
                    </div>
                    <span class="step-mark" aria-hidden="true">01</span>
                </div>

                <form id="profileForm">
                    <label>Full name
                        <input name="full_name" maxlength="150" required placeholder="Enter full name">
                    </label>
                    <label>Email address
                        <input name="email" type="email" maxlength="190" required placeholder="name@example.com">
                    </label>
                    <button class="button" id="submitBtn">Create profile and send QR</button>
                    <p id="formMessage" class="message" role="status"></p>
                </form>

                <div id="qrStaging" class="qr-staging"></div>
            </section>

            <section class="panel profiles-panel">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">DIRECTORY</p>
                        <h2>Profiles</h2>
                    </div>
                    <span class="count"><?= count($profiles) ?> profiles</span>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>QR code</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$profiles): ?>
                                <tr>
                                    <td colspan="4" class="empty">No profiles yet.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($profiles as $p): ?>
                                <tr>
                                    <td><strong><?= e($p['full_name']) ?></strong></td>
                                    <td><?= e($p['email']) ?></td>
                                    <td>
                                        <?php if ($p['qr_image']): ?>
                                            <a href="<?= e($p['qr_image']) ?>" target="_blank" rel="noopener">
                                                <img class="qr-thumb" src="<?= e($p['qr_image']) ?>" alt="QR code">
                                            </a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($p['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <script src="app.js"></script>
</body>

</html>