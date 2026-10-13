<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>Scan Attendance</title>
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
				<a href="scan.php" aria-current="page">Scan</a>
				<a href="attendance.php">Records</a>
			</nav>
		</div>
	</header>

	<main class="container scan-page">
		<section class="page-intro">
			<div>
				<p class="eyebrow">CHECK-IN STATION</p>
				<h1>Scan attendance</h1>
				<p class="muted">Your first scan checks you in. Scan again later today to check out. Upload an image or attendance pass PDF if you cannot use the camera.</p>
			</div>
		</section>

		<div class="scan-content">
			<section class="panel scanner-panel">
				<p class="eyebrow">CAMERA SCANNER</p>
				<h2>Scan to check in or out</h2>
				<div id="reader" class="reader"></div>
			</section>

			<section class="panel manual-panel">
				<p class="eyebrow">MANUAL CHECK-IN</p>
				<h2>Enter code manually</h2>
				<label for="qrInput">
					QR code or pass link
					<input id="qrInput" placeholder="Paste QR code or pass link">
				</label>
				<button id="checkInBtn" class="button" type="button">Record attendance</button>
				<div class="qr-upload">
					<label for="qrFile">Or upload a QR image or pass PDF</label>
					<input id="qrFile" type="file" accept="image/png,image/jpeg,image/webp,application/pdf,.pdf">
				</div>
				<p id="scanMessage" class="message" role="status"></p>
			</section>
		</div>
		<div id="fileReader" class="file-reader" aria-hidden="true"></div>
	</main>

	<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
	<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
	<script src="scan.js"></script>
</body>
</html>
