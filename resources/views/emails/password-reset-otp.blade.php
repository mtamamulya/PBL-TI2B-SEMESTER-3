<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Kode OTP pemulihan akun</title></head>
<body style="margin:0;padding:24px;background:#F3F4F6;font-family:Arial,Helvetica,sans-serif;color:#111827;">
    <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px;">
        <h2 style="margin:0 0 8px;font-size:18px;">Pemulihan akun Repositori Kegiatan Prodi</h2>
        <p style="margin:0 0 16px;font-size:14px;color:#374151;">Gunakan kode berikut untuk mengatur ulang kata sandi akun Repositori Kegiatan Prodi:</p>
        <p style="margin:0 0 16px;font-size:32px;font-weight:bold;letter-spacing:8px;text-align:center;color:#023389;">{{ $code }}</p>
        <p style="margin:0 0 8px;font-size:13px;color:#374151;">Kode berlaku selama {{ $expiresInMinutes }} menit dan hanya dapat dipakai sekali.</p>
        <p style="margin:0;font-size:12px;color:#6B7280;">Jika Anda tidak meminta pemulihan, abaikan email ini dan jangan bagikan kode kepada siapa pun.</p>
    </div>
</body>
</html>