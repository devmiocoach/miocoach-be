<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachInvitation;
use App\Modules\Users\Notifications\CoachInvitationNotification;
use App\Services\InviteTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SendInvitationAction
{
    private const MAX_PENDING = 20;

    private const EXPIRY_DAYS = 7;

    public function __construct(private readonly InviteTokenService $inviteTokenService) {}

    public function handle(Coach $coach, string $email): CoachInvitation
    {
        // Revoca + creazione atomiche: se il create fallisce il vecchio invito
        // non viene perso. La notifica email è fuori dalla transazione per
        // evitare side-effect su dati non ancora committati.
        // Il count check è dentro la transazione con lockForUpdate per evitare
        // che due richieste concorrenti superino entrambe il limite MAX_PENDING.
        $invitation = DB::transaction(function () use ($coach, $email) {
            $pendingCount = $coach->pendingInvitations()->lockForUpdate()->count();

            if ($pendingCount >= self::MAX_PENDING) {
                throw ValidationException::withMessages([
                    'email' => ['Hai raggiunto il limite di inviti pendenti. Revoca quelli non utilizzati prima di inviarne altri.'],
                ]);
            }

            $coach->invitations()
                ->where('email', mb_strtolower($email))
                ->where('status', 'pending')
                ->update(['status' => 'revoked']);

            return CoachInvitation::create([
                'coach_id' => $coach->id,
                'email' => mb_strtolower($email),
                'token' => $this->inviteTokenService->generate($coach->id, mb_strtolower($email)),
                'status' => 'pending',
                'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            ]);
        });

        Notification::route('mail', $invitation->email)
            ->notify(new CoachInvitationNotification($invitation, $coach));

        return $invitation;
    }
}
