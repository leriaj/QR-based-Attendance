const form = document.getElementById('profileForm');
const message = document.getElementById('formMessage');
const button = document.getElementById('submitBtn');
const staging = document.getElementById('qrStaging');

form.addEventListener('submit', async (event) => {
event.preventDefault();

message.className = 'message';
message.textContent = '';
button.disabled = true;
button.textContent = 'Generating QR code…';

try {
const formData = new FormData(form);

const bytes = crypto.getRandomValues(new Uint8Array(32));
const token = Array.from(bytes, b =>
  b.toString(16).padStart(2, '0')
).join('');

staging.innerHTML = '';

new QRCode(staging, {
  text: 'QRATTENDANCE:V1:' + token,
  width: 280,
  height: 280,
  correctLevel: QRCode.CorrectLevel.M
});

await new Promise(resolve => setTimeout(resolve, 100));

const canvas = staging.querySelector('canvas');

if (!canvas) {
  throw new Error('QR image generation failed. Check your internet connection.');
}

const quietZone = 20;
const paddedCanvas = document.createElement('canvas');
paddedCanvas.width = canvas.width + quietZone * 2;
paddedCanvas.height = canvas.height + quietZone * 2;
const paddedContext = paddedCanvas.getContext('2d');
paddedContext.fillStyle = '#fff';
paddedContext.fillRect(0, 0, paddedCanvas.width, paddedCanvas.height);
paddedContext.drawImage(canvas, quietZone, quietZone);

if (!window.jspdf || !window.jspdf.jsPDF) {
  throw new Error('PDF generation is unavailable. Check your internet connection and try again.');
}

const pdf = new window.jspdf.jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
const profileName = String(formData.get('full_name')).trim();
const profileEmail = String(formData.get('email')).trim();
const nameLines = pdf.splitTextToSize(profileName, 150);

pdf.setFillColor(240, 244, 249);
pdf.rect(0, 0, 210, 297, 'F');
pdf.setFillColor(255, 255, 255);
pdf.roundedRect(17, 17, 176, 263, 4, 4, 'F');
pdf.setFillColor(23, 57, 87);
pdf.roundedRect(17, 17, 176, 58, 4, 4, 'F');
pdf.rect(17, 68, 176, 7, 'F');
pdf.setTextColor(185, 221, 255);
pdf.setFont('helvetica', 'bold');
pdf.setFontSize(10);
pdf.text('QR ATTENDANCE', 29, 34);
pdf.setTextColor(255, 255, 255);
pdf.setFontSize(23);
pdf.text('Your attendance pass', 29, 49);
pdf.setFont('helvetica', 'normal');
pdf.setFontSize(10);
pdf.text('A personal check-in code for your daily attendance.', 29, 62);
pdf.setTextColor(105, 120, 140);
pdf.setFont('helvetica', 'bold');
pdf.setFontSize(9);
pdf.text('ASSIGNED TO', 29, 94);
pdf.setTextColor(30, 46, 66);
pdf.setFontSize(17);
pdf.text(nameLines, 29, 105);
const emailY = 105 + (nameLines.length - 1) * 7 + 9;
pdf.setTextColor(105, 120, 140);
pdf.setFont('helvetica', 'normal');
pdf.setFontSize(10);
pdf.text(profileEmail, 29, emailY);
const qrTop = Math.max(122, emailY + 11);
pdf.setFillColor(240, 244, 249);
pdf.roundedRect(57, qrTop - 6, 96, 96, 2, 2, 'F');
pdf.addImage(paddedCanvas.toDataURL('image/png'), 'PNG', 65, qrTop + 2, 80, 80);
pdf.setTextColor(23, 57, 87);
pdf.setFont('helvetica', 'bold');
pdf.setFontSize(9);
pdf.text('SCAN THIS CODE TO CHECK IN', 105, qrTop + 86, { align: 'center' });
pdf.setTextColor(105, 120, 140);
pdf.setFont('helvetica', 'normal');
pdf.setFontSize(8);
pdf.text('QR ATTENDANCE SYSTEM PASS | V1', 105, qrTop + 93, { align: 'center' });
pdf.setDrawColor(219, 228, 239);
pdf.line(29, 232, 181, 232);
pdf.setTextColor(30, 46, 66);
pdf.setFontSize(11);
pdf.text('Keep your pass ready', 29, 245);
pdf.setTextColor(105, 120, 140);
pdf.setFont('helvetica', 'normal');
pdf.setFontSize(9);
pdf.text(pdf.splitTextToSize('Present this QR code when checking in. Your profile can record one attendance entry per day.', 145), 29, 254);
pdf.setTextColor(52, 120, 189);
pdf.setFont('helvetica', 'bold');
pdf.text('QR ATTENDANCE', 29, 273);

const pdfData = pdf.output('datauristring').split(',')[1];

formData.set('qr_token', token);
formData.set('qr_png', paddedCanvas.toDataURL('image/png'));
formData.set('qr_pdf', 'data:application/pdf;base64,' + pdfData);

const response = await fetch('create_profile.php', {
  method: 'POST',
  body: formData
});

const responseText = await response.text();

let result;

try {
  result = JSON.parse(responseText);
} catch (parseError) {
  console.error('PHP response:', responseText);

  throw new Error(
    'PHP returned an error instead of JSON. Open the browser Console (F12) to see the PHP error.'
  );
}

if (!response.ok || !result.ok) {
  throw new Error(result.message || 'Could not save profile.');
}

message.className = 'message success';
message.textContent = result.message;

form.reset();

setTimeout(() => location.reload(), 2200);

} catch (error) {
console.error(error);

message.className = 'message error';
message.textContent = error.message || 'Something went wrong.';

} finally {
button.disabled = false;
button.textContent = 'Create profile and send QR';
}
});