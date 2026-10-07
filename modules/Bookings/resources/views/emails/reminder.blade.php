<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Promemoria sessione</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h1 style="font-size: 22px;">Promemoria sessione</h1>
    <p>Ciao {{ $booking->guest_name ?? $booking->client?->user?->name ?? 'cliente' }},</p>
    <p>ti ricordiamo la sessione con {{ $coachName }}.</p>

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
</body>
</html>
