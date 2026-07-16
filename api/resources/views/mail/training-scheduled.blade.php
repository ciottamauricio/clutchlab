<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Practice scheduled</title></head>
<body style="margin:0;padding:24px;background:#f3f5f8;font-family:Arial,Helvetica,sans-serif;color:#11161c;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:10px;border:1px solid #dde2ea;">
    <tr><td style="padding:28px 30px;">
      <p style="margin:0 0 4px;font:600 12px/1 monospace;letter-spacing:0.12em;text-transform:uppercase;color:#8b96a5;">Practice scheduled</p>
      <h1 style="margin:0 0 18px;font-size:22px;color:#11161c;">You're on the roster</h1>

      <p style="margin:0 0 16px;font-size:15px;line-height:1.5;">
        Hi {{ $name }}, <strong>{{ $team }}</strong> has a practice scheduled — you're expected to attend.
      </p>

      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;border-collapse:collapse;">
        <tr><td style="padding:14px 16px;background:#f3f5f8;border-radius:8px;">
          <div style="font-size:17px;font-weight:600;margin-bottom:4px;">{{ $title }}</div>
          <div style="font:400 13px/1.4 monospace;color:#515c6b;">
            {{ $when->format('l, j F') }} · {{ $when->format('H:i') }} UTC{{ $duration ? " · {$duration} min" : '' }}
          </div>
          @if (count($tactics))
            <div style="font-size:13px;color:#515c6b;margin-top:8px;">Drilling: {{ implode(', ', $tactics) }}</div>
          @endif
        </td></tr>
      </table>

      <a href="{{ rtrim(config('app.url'), '/') }}/trainings"
         style="display:inline-block;padding:10px 18px;background:#10151b;color:#ffffff;text-decoration:none;border-radius:7px;font-size:14px;font-weight:600;">
        See the session
      </a>

      <p style="margin:22px 0 0;font-size:13px;color:#8b96a5;">
        Let your team know if you can make it — {{ config('app.name') }}
      </p>
    </td></tr>
  </table>
</body>
</html>
