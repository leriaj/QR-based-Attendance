const message = document.getElementById('scanMessage');
const input = document.getElementById('qrInput');
const button = document.getElementById('checkInBtn');
const qrFileInput = document.getElementById('qrFile');
const reader = document.getElementById('reader');
let busy = false;
let reloadPending = false;

function setScannerState(state) {
  reader.dataset.state = state;
}

async function decodePdf(file, fileScanner) {
  if (!window.pdfjsLib) {
    throw new Error('PDF reader is unavailable. Check your internet connection and try again.');
  }

  window.pdfjsLib.GlobalWorkerOptions.workerSrc =
    'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

  const pdfDocument = await window.pdfjsLib.getDocument({ data: await file.arrayBuffer() }).promise;

  try {
    const pdfPage = await pdfDocument.getPage(1);
    const baseViewport = pdfPage.getViewport({ scale: 1 });
    const scale = Math.min(2, 1800 / Math.max(baseViewport.width, baseViewport.height));
    const viewport = pdfPage.getViewport({ scale });
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    canvas.width = Math.ceil(viewport.width);
    canvas.height = Math.ceil(viewport.height);

    await pdfPage.render({ canvasContext: context, viewport }).promise;

    const image = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));

    if (!image) {
      throw new Error('Could not render the PDF page.');
    }

    return await fileScanner.scanFile(new File([image], 'attendance-pass.png', { type: 'image/png' }), false);
  } finally {
    await pdfDocument.destroy();
  }
}

async function checkIn(value) {
  if (busy || !value) return;
  busy = true;
  setScannerState('detected');
  message.className = 'message';
  message.textContent = 'Recording attendance…';
  try {
    const data = new FormData();
    data.set('qr_value', value.trim());
    const response = await fetch('checkin.php', { method: 'POST', body: data });
    const result = await response.json();
    if (!response.ok || !result.ok) throw new Error(result.message || 'Check-in failed.');
    setScannerState('success');
    message.className = 'message success';
    const recordedTime = result.action === 'check_in' ? result.time_in : result.time_out;
    const emailMessage = result.email_sent
      ? ' A confirmation email was sent.'
      : ' The confirmation email could not be sent.';
    message.textContent = result.message + ' Time: ' + recordedTime + '.' + emailMessage;
    reloadPending = true;
    button.disabled = true;
    window.setTimeout(() => window.location.reload(), 2500);
  } catch (error) {
    setScannerState('error');
    message.className = 'message error';
    message.textContent = error.message || 'Check-in failed.';
  } finally {
    if (!reloadPending) busy = false;
  }
}
button.addEventListener('click', () => checkIn(input.value));
if (window.Html5Qrcode) {
  const scanner = new Html5Qrcode('reader');
  const fileScanner = new Html5Qrcode('fileReader');

  qrFileInput.addEventListener('change', async () => {
    const file = qrFileInput.files && qrFileInput.files[0];
    if (!file || busy) return;

    if (file.size > 15 * 1024 * 1024) {
      message.className = 'message error';
      message.textContent = 'Choose a file smaller than 15 MB.';
      qrFileInput.value = '';
      return;
    }

    qrFileInput.disabled = true;
    message.className = 'message';
    const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    message.textContent = isPdf ? 'Reading attendance pass PDF…' : 'Reading QR image…';

    try {
      const decoded = isPdf
        ? await decodePdf(file, fileScanner)
        : await fileScanner.scanFile(file, false);
      input.value = decoded;
      await checkIn(decoded);
    } catch (error) {
      setScannerState('error');
      message.className = 'message error';
      message.textContent = 'Could not read a QR code from that file. Use a clear PNG, JPG, WEBP, or attendance pass PDF.';
    } finally {
      qrFileInput.disabled = false;
      qrFileInput.value = '';
      try {
        await fileScanner.clear();
      } catch (error) {
        console.debug('QR image scanner cleanup:', error);
      }
    }
  });

  setScannerState('scanning');
  Html5Qrcode.getCameras().then(cameras => {
    if (!cameras || !cameras.length) throw new Error('No camera found.');
    return scanner.start(cameras[0].id, { fps: 10, qrbox: { width: 230, height: 230 } }, decoded => { input.value = decoded; setScannerState('detected'); checkIn(decoded); }, () => { if (!busy && reader.dataset.state !== 'success' && reader.dataset.state !== 'error') setScannerState('scanning'); });
  }).catch(() => { setScannerState('error'); reader.innerHTML = '<p class="muted">Camera unavailable. Paste the QR token into the field below.</p>'; });
} else {
  setScannerState('error');
  reader.innerHTML = '<p class="muted">Camera scanner unavailable. Paste the QR token into the field beside it.</p>';
  qrFileInput.addEventListener('change', () => {
    message.className = 'message error';
    message.textContent = 'QR scanning is unavailable. Check your internet connection and try again.';
  });
}
