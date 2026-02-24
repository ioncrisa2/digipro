@component('mail::message')
{{-- Header custom --}}
<div style="text-align:center; margin-bottom:24px;">
    <div style="
        display:inline-flex;
        align-items:center;
        gap:8px;
        padding:8px 14px;
        border-radius:999px;
        background:#020617;
        color:#e5e7eb;
        font-size:12px;
        text-transform:uppercase;
        letter-spacing:0.08em;
    ">
        <span style="font-weight:700;">DIGI<span style="color:#38bdf8;">PRO</span></span>
        <span style="opacity:.7;">Password Reset</span>
    </div>
</div>

# Halo, {{ $user->name ?? 'Pengguna DIGIPRO' }}

Kami menerima permintaan untuk mengatur ulang password akun Anda.

@component('mail::button', ['url' => $url])
Atur Ulang Password
@endcomponent

Jika Anda tidak merasa meminta pengaturan ulang password, abaikan email ini.
Password Anda saat ini akan tetap aman.

Terima kasih,<br>
**Tim DIGIPRO**
<small style="color:#6b7280;">KJPP Henricus Judi Adrianto & Rekan</small>
@endcomponent
