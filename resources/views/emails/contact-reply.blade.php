<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Georgia, serif; color: #334155; margin: 0; padding: 0; background: #f8fafc; }
    .wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
    .header { background: #0a1f52; padding: 28px 32px; }
    .header h1 { margin: 0; color: #f5c518; font-size: 20px; letter-spacing: 0.02em; }
    .header p { margin: 6px 0 0; color: rgba(255,255,255,0.6); font-size: 12px; }
    .body { padding: 28px 32px; }
    .greeting { font-size: 16px; font-weight: bold; color: #0a1f52; margin-bottom: 16px; }
    .reply-box { background: #f8fafc; border-left: 4px solid #f5c518; border-radius: 4px; padding: 16px 20px; font-size: 14px; line-height: 1.8; color: #334155; white-space: pre-wrap; }
    .divider { border: none; border-top: 1px solid #f1f5f9; margin: 24px 0; }
    .original { background: #f8fafc; border-radius: 8px; padding: 16px 20px; font-size: 13px; color: #64748b; }
    .original p { margin: 0 0 6px; }
    .footer { background: #f8fafc; padding: 16px 32px; font-size: 11px; color: #94a3b8; text-align: center; border-top: 1px solid #f1f5f9; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>School Response</h1>
      <p>Re: {{ $contactMessage->subject }}</p>
    </div>
    <div class="body">
      <p class="greeting">Dear {{ $contactMessage->name }},</p>
      <div class="reply-box">{{ $replyBody }}</div>
      <hr class="divider">
      <div class="original">
        <p><strong>Your original message:</strong></p>
        <p>{{ $contactMessage->message }}</p>
      </div>
    </div>
    <div class="footer">
      This is an official reply from the school administration. Please do not reply directly to this email.
    </div>
  </div>
</body>
</html>