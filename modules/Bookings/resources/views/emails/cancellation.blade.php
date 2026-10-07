<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Prenotazione cancellata</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h1 style="font-size: 22px;">Prenotazione cancellata</h1>
    <p>Ciao {{ $booking->guest_name ?? $booking->client?->user?->name ?? 'cliente' }},</p>
    <p>la sessione con {{ $coachName }} del {{ $booking->starts_at->format('d/m/Y H:i') }} è stata cancellata.</p>

    <p><strong>Motivo:</strong> {{ $reason }}</p>
</body>
</html>
