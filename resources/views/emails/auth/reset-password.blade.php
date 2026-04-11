@component('mail::message')
# Atur Ulang Password DigiPro by KJPP HJAR

Halo, {{ $user->name ?? 'Pengguna DigiPro by KJPP HJAR' }}.

Kami menerima permintaan untuk mengatur ulang password akun DigiPro by KJPP HJAR Anda.

Silakan buka tautan berikut untuk melanjutkan proses reset password:

@component('mail::panel')
<a href="{{ $url }}" style="word-break: break-all; color: #0f766e; text-decoration: none;">{{ $url }}</a>
@endcomponent

Jika email client Anda mendukung tombol aksi, Anda juga bisa menggunakan tombol berikut:

@component('mail::button', ['url' => $url])
Atur Ulang Password
@endcomponent

Jika Anda tidak meminta reset password, abaikan email ini. Password Anda saat ini tetap aman dan tidak berubah.

Salam,<br>
**Tim DigiPro by KJPP HJAR**  
KJPP Henricus Judi Adrianto & Rekan
@endcomponent
