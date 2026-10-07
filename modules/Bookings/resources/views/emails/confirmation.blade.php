<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Conferma prenotazione</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h1 style="font-size: 22px;">Prenotazione confermata</h1>
    <p>Ciao {{ $booking->guest_name ?? $booking->client?->user?->name ?? 'cliente' }},</p>
    <p>la tua sessione con {{ $coachName }} è confermata.</p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
        <tr>
            <td style="padding: 4px 16px 4px 0;"><strong>Data e ora</strong></td>
            <td>{{ $booking->starts_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td style="padding: 4px 16px 4px 0;"><strong>Durata</strong></td>
            <td>{{ $booking->duration_minutes }} minuti</td>
        </tr>
        <tr>
            <td style="padding: 4px 16px 4px 0;"><strong>Servizio</strong></td>
            <td>{{ $booking->service_type->value }}</td>
        </tr>
    </table>

    <p>
        <a href="{{ $calendarUrl }}" style="display: inline-block; padding: 12px 16px; background: #111827; color: #ffffff; text-decoration: none; border-radius: 6px;">Aggiungi a Google Calendar</a>
    </p>
    <p>
        <a href="{{ $cancelUrl }}" style="display: inline-block; padding: 12px 16px; color: #b91c1c;">Cancella</a>
    </p>
</body>
</html>
