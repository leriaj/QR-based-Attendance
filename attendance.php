<?php
require __DIR__ . '/config.php';

$profiles = db()->query(
    'SELECT id, full_name, email, created_at FROM profiles ORDER BY full_name'
)->fetchAll();

$today = date('Y-m-d');
$requestedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$monthDate = preg_match('/^\d{4}-\d{2}$/', $requestedMonth)
    ? DateTimeImmutable::createFromFormat('!Y-m', $requestedMonth)
    : false;

if (!$monthDate || $monthDate->format('Y-m') !== $requestedMonth) {
    $monthDate = new DateTimeImmutable(date('Y-m-01'));
}

$monthStart = $monthDate->modify('first day of this month');
$monthEnd = $monthDate->modify('last day of this month');
$daysInMonth = (int) $monthDate->format('t');
$selectedProfile = null;
$requestedProfileId = isset($_GET['profile']) ? filter_var($_GET['profile'], FILTER_VALIDATE_INT) : false;

foreach ($profiles as $profile) {
    if ($requestedProfileId && (int) $profile['id'] === (int) $requestedProfileId) {
        $selectedProfile = $profile;
        break;
    }
}

$attendanceByDate = array();
$attendeesByDate = array();
$monthlySummary = array('total_attendees' => 0, 'total_checkins' => 0);

$stmt = db()->prepare(
	'SELECT COUNT(DISTINCT profile_id) AS total_attendees, COUNT(*) AS total_checkins ' .
	'FROM attendance WHERE attendance_date BETWEEN ? AND ?'
);
$stmt->execute(array($monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')));
$monthlySummary = $stmt->fetch();

$stmt = db()->prepare(
	'SELECT attendance_date, COUNT(DISTINCT profile_id) AS attendee_count ' .
	'FROM attendance WHERE attendance_date BETWEEN ? AND ? GROUP BY attendance_date'
);
$stmt->execute(array($monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')));

foreach ($stmt->fetchAll() as $row) {
	$attendeesByDate[$row['attendance_date']] = (int) $row['attendee_count'];
}

if ($selectedProfile) {
    $stmt = db()->prepare(
        'SELECT attendance_date, time_in, time_out FROM attendance ' .
        'WHERE profile_id = ? AND attendance_date BETWEEN ? AND ?'
    );
    $stmt->execute(array(
        (int) $selectedProfile['id'],
        $monthStart->format('Y-m-d'),
        $monthEnd->format('Y-m-d')
    ));

    foreach ($stmt->fetchAll() as $row) {
        $attendanceByDate[$row['attendance_date']] = $row;
    }
}

$previousMonth = $monthDate->modify('-1 month');
$nextMonth = $monthDate->modify('+1 month');
$profileQuery = $selectedProfile ? '&profile=' . (int) $selectedProfile['id'] : '';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Attendance Calendar</title>
	<link rel="stylesheet" href="style.css">
	<link rel="stylesheet" href="design.css">
</head>
<body>
	<header class="topbar">
		<div class="topbar-inner">
			<a class="brand" href="index.php">
				<span class="logo">QR</span>
				<span>QR Attendance</span>
			</a>
			<nav aria-label="Main navigation">
				<a href="index.php">Profiles</a>
				<a href="scan.php">Scan</a>
				<a href="attendance.php" aria-current="page">Records</a>
			</nav>
		</div>
	</header>

	<main class="container attendance-page">
		<section class="page-intro">
			<div>
				<p class="eyebrow">ATTENDANCE OVERVIEW</p>
				<h1>Monthly calendar</h1>
				<p class="muted">Review check-ins, check-outs, and absences by day.</p>
			</div>
			<a class="button button-secondary" href="scan.php">New check-in</a>
		</section>

		<section class="panel calendar-panel" aria-label="Attendance calendar">
			<div class="calendar-toolbar">
				<div>
					<p class="eyebrow"><?= $selectedProfile ? 'SELECTED PROFILE' : 'MONTHLY OVERVIEW' ?></p>
					<h2><?= $selectedProfile ? e($selectedProfile['full_name']) : ($profiles ? 'All profiles' : 'No profiles yet') ?></h2>
					<?php if ($selectedProfile): ?>
						<p class="muted"><?= e($selectedProfile['email']) ?></p>
					<?php endif; ?>
				</div>
				<div class="calendar-month-nav">
					<a class="button button-secondary" href="?month=<?= e($previousMonth->format('Y-m')) ?><?= $profileQuery ?>" aria-label="Previous month">Previous</a>
					<h2><?= e($monthDate->format('F Y')) ?></h2>
					<a class="button button-secondary" href="?month=<?= e($nextMonth->format('Y-m')) ?><?= $profileQuery ?>" aria-label="Next month">Next</a>
				</div>
                </div>
                    <?php if (!$selectedProfile && $profiles): ?>
                        <div class="calendar-summary" aria-label="Monthly attendee totals">
                            <div><span>Attendees this month</span><strong><?= (int) $monthlySummary['total_attendees'] ?></strong></div>
                            <div><span>Total check-ins</span><strong><?= (int) $monthlySummary['total_checkins'] ?></strong></div>
                </div>
                <?php endif; ?>

			<div class="calendar-controls">
				<div class="calendar-profile-controls">
					<div class="profile-search">
						<label for="profileSearch">Search profiles
							<input id="profileSearch" type="search" placeholder="Name or email" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="profileSearchResults">
						</label>
						<div id="profileSearchResults" class="profile-search-results" role="listbox" aria-label="Matching profiles" hidden></div>
					</div>
					<form method="get" action="attendance.php">
						<input type="hidden" name="month" value="<?= e($monthDate->format('Y-m')) ?>">
						<label for="profileSelect">
							Profile
							<select id="profileSelect" name="profile" <?= !$profiles ? 'disabled' : '' ?>>
								<option value="" data-search="all profiles" <?= !$selectedProfile ? 'selected' : '' ?>>All profiles</option>
								<?php foreach ($profiles as $profile): ?>
									<option value="<?= (int) $profile['id'] ?>" data-search="<?= e(strtolower($profile['full_name'] . ' ' . $profile['email'])) ?>" <?= $selectedProfile && (int) $selectedProfile['id'] === (int) $profile['id'] ? 'selected' : '' ?>>
										<?= e($profile['full_name']) ?> · <?= e($profile['email']) ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>
					</form>
				</div>
				<label for="statusFilter">Filter days
					<select id="statusFilter" <?= !$selectedProfile ? 'disabled' : '' ?>>
						<option value="all">All statuses</option>
						<option value="complete">Checked in &amp; out</option>
						<option value="open">Needs checkout</option>
						<option value="absent">Absent</option>
						<option value="pending">Not checked in today</option>
					</select>
				</label>
			</div>

			<?php if ($selectedProfile): ?>
				<div class="calendar-legend" aria-label="Calendar status legend">
					<span><i class="legend-swatch status-complete"></i>Checked in &amp; out</span>
					<span><i class="legend-swatch status-open"></i>Needs checkout</span>
					<span><i class="legend-swatch status-absent"></i>Absent</span>
					<span><i class="legend-swatch status-neutral"></i>Upcoming / not registered</span>
				</div>
			<?php endif; ?>

			<div class="calendar-grid" role="grid" aria-label="<?= e($monthDate->format('F Y')) ?> attendance">
				<?php foreach (array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun') as $weekday): ?>
					<div class="calendar-weekday" role="columnheader"><?= $weekday ?></div>
				<?php endforeach; ?>

				<?php for ($blank = 1; $blank < (int) $monthStart->format('N'); $blank++): ?>
					<div class="calendar-empty" aria-hidden="true"></div>
				<?php endfor; ?>

				<?php for ($dayNumber = 1; $dayNumber <= $daysInMonth; $dayNumber++): ?>
					<?php
					$dayDate = $monthStart->modify('+' . ($dayNumber - 1) . ' days');
					$dateKey = $dayDate->format('Y-m-d');
					$record = isset($attendanceByDate[$dateKey]) ? $attendanceByDate[$dateKey] : null;

					$attendeeCount = isset($attendeesByDate[$dateKey]) ? $attendeesByDate[$dateKey] : 0;

					if (!$selectedProfile) {
						$status = 'overview';
						$statusLabel = $attendeeCount . ($attendeeCount === 1 ? ' attendee' : ' attendees');
					} elseif ($record && $record['time_out'] !== null) {
						$status = 'complete';
						$statusLabel = 'Complete';
					} elseif ($record) {
						$status = 'open';
						$statusLabel = 'Needs checkout';
					} elseif ($dateKey < substr($selectedProfile['created_at'], 0, 10)) {
						$status = 'neutral';
						$statusLabel = 'Not registered';
					} elseif ($dateKey < $today) {
						$status = 'absent';
						$statusLabel = 'Absent';
					} elseif ($dateKey === $today) {
						$status = 'pending';
						$statusLabel = 'Not checked in';
					} else {
						$status = 'neutral';
						$statusLabel = 'Upcoming';
					}
					?>
					<div class="calendar-day status-<?= e($status) ?>" role="gridcell" data-day-status="<?= e($status) ?>">
						<div class="calendar-day-heading">
							<span class="calendar-day-number"><?= $dayNumber ?></span>
							<span class="calendar-day-status"><?= e($statusLabel) ?></span>
						</div>
						<?php if ($selectedProfile && $record): ?>
							<time class="calendar-time" datetime="<?= e($record['time_in']) ?>">In <?= e(substr($record['time_in'], 11, 5)) ?></time>
							<?php if ($record['time_out']): ?>
								<time class="calendar-time" datetime="<?= e($record['time_out']) ?>">Out <?= e(substr($record['time_out'], 11, 5)) ?></time>
							<?php endif; ?>
						<?php elseif (!$selectedProfile): ?>
							<span class="calendar-time"><?= $attendeeCount ? $attendeeCount . ($attendeeCount === 1 ? ' attendee' : ' attendees') : 'No check-ins' ?></span>
						<?php endif; ?>
					</div>
				<?php endfor; ?>
			</div>
		</section>
	</main>
	<script src="records.js"></script>
</body>
</html>