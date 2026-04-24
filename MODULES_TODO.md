# Moduli da costruire

Questo file traccia gli endpoint e le funzionalità che esulano dai moduli esistenti (Auth, Users, WorkoutPlans) e vanno implementati in nuovi moduli dedicati.

---

## Modulo: Billing

**Path:** `app/Modules/Billing/`

Gestisce pacchetti sessioni e piani di abbonamento del coach.

### Endpoint

#### Packages
| Metodo | Path | Descrizione |
|---|---|---|
| `POST` | `/api/v1/coaches/me/packages` | Crea un pacchetto sessioni |
| `GET` | `/api/v1/coaches/me/packages` | Lista pacchetti del coach |
| `PUT` | `/api/v1/coaches/me/packages/{id}` | Aggiorna un pacchetto |
| `DELETE` | `/api/v1/coaches/me/packages/{id}` | Elimina un pacchetto |

**Modello `Package`** — campi indicativi:
```
id, coach_id (FK coaches), name, description, session_count,
price, validity_days, is_active, created_at, updated_at
```

#### Subscription Plans
| Metodo | Path | Descrizione |
|---|---|---|
| `POST` | `/api/v1/coaches/me/subscription-plans` | Crea un piano abbonamento |
| `PUT` | `/api/v1/coaches/me/subscription-plans/{id}` | Aggiorna un piano |
| `DELETE` | `/api/v1/coaches/me/subscription-plans/{id}` | Elimina un piano |
| `GET` | `/api/v1/coaches/me/subscription-plans` | Lista piani del coach |

**Modello `SubscriptionPlan`** — campi indicativi:
```
id, coach_id (FK coaches), name, description, price_monthly, price_yearly,
sessions_per_month, features (json), is_active, stripe_price_id,
created_at, updated_at
```

### Responsabilità sui dati cliente
Le colonne `subscription_expires_at` e `subscription_sessions_remaining` vivono sulla tabella `clients` (aggiunte dal modulo Users per supportare il listing) ma vengono **scritte e mantenute dal modulo Billing** quando si attiva/rinnova/scade un abbonamento.

### Note implementative
- Integrazione con Stripe Connect (il coach ha già `stripe_connect_id` e `stripe_onboarding_completed` nella tabella `coaches`)
- I prezzi dei piani andrebbero sincronizzati su Stripe come `Price` objects
- Auth: `middleware('role:coach')` su tutti gli endpoint

---

## Modulo: Reviews

**Path:** `app/Modules/Reviews/`

Gestisce le recensioni dei clienti ai coach. I dati di rating/recensioni sono referenziati nel profilo pubblico coach (`GET /coaches/{id}/public`) che è nel modulo Users.

### Endpoint

| Metodo | Path | Descrizione |
|---|---|---|
| `POST` | `/api/v1/coaches/{id}/reviews` | Il cliente lascia una recensione |
| `GET` | `/api/v1/coaches/{id}/reviews` | Lista pubblica recensioni coach |
| `DELETE` | `/api/v1/reviews/{id}` | Eliminazione (admin o proprietario) |

**Modello `Review`** — campi indicativi:
```
id, coach_id (FK coaches), client_id (FK clients), rating (tinyInt 1-5),
body (text nullable), is_visible (boolean), created_at, updated_at
```

### Integrazione con Users Module
`GET /api/v1/coaches/{id}/public` (nel modulo Users) deve esporre:
- `rating`: media calcolata da `reviews.rating` WHERE `coach_id = $id AND is_visible = true`
- `reviews_count`: count degli stessi record

Il modulo Users non interroga direttamente la tabella `reviews` ma può farlo tramite una relazione Eloquent su `Coach` definita nel modulo Reviews (oppure il valore viene cachato su `coaches.rating_avg` e `coaches.reviews_count` per performance).

---

## Modulo: Bookings *(futuro)*

**Path:** `app/Modules/Bookings/`

Gestisce la prenotazione di sessioni da parte dei clienti, collegandosi agli slot di disponibilità del coach (`coach_availabilities` — modulo Users) e ai pagamenti (modulo Billing).

### Responsabilità sui dati cliente
- La colonna `next_booking` nei listing dei clienti (`GET /coaches/me/clients`) viene **popolata dal modulo Bookings** tramite join/relazione sulla tabella `bookings`.
- Il campo `storico ultime 10 sessioni` e `prossima sessione` nella scheda cliente (`GET /coaches/me/clients/:id`) sono a carico del modulo Bookings.

---

## Modulo: Notifications

**Path:** `app/Modules/Notifications/`

Gestisce le notifiche in-app e push verso coach e clienti. Necessario per supportare i cron job definiti nella spec client management.

### Modello `Notification`
```
id, user_id (FK users), type (string), body (text), read_at (timestamp nullable),
metadata (json nullable), created_at, updated_at
```

### Tipi di notifica previsti
| Tipo | Trigger |
|---|---|
| `SUBSCRIPTION_EXPIRING` | subscriptionExpiresAt tra oggi e +7 giorni |
| `CLIENT_INACTIVE` | nessuna sessione completata negli ultimi N giorni |
| `CHECKIN_REMINDER` | CheckinFormAssignment con frequenza corrispondente al giorno |

### Push Notifications — FCM
- Integrazione Firebase Cloud Messaging (FCM) per inviare push al coach
- Il device token FCM del coach va salvato (es. su `coaches.fcm_token` o tabella dedicata `device_tokens`)

### Cron Jobs (BullMQ / Laravel Scheduler)
> I job vanno in questo modulo perché il loro scopo è creare Notification e spedire push. Dipendono da dati di Billing e Bookings.

#### Job: `checkExpiringSubscriptions`
- Schedule: ogni giorno 08:00 UTC
- Trova clienti con `subscription_expires_at` tra oggi e +7 giorni (status active, non già notificati oggi)
- Per ogni cliente: crea `Notification` per il coach (tipo SUBSCRIPTION_EXPIRING)
- Invia push via FCM al coach
- **Dipende da:** colonne `subscription_expires_at` su `clients` (scritte da Billing)

#### Job: `checkInactiveClients`
- Schedule: ogni giorno 08:00 UTC
- Trova clienti senza sessione completata negli ultimi N giorni (N = impostazione coach, default 14)
- Per ogni cliente inattivo: crea `Notification` per il coach (tipo CLIENT_INACTIVE)
- Invia push via FCM
- **Dipende da:** tabella `bookings/sessions` del modulo Bookings

#### Job: `sendCheckinReminders`
- Schedule: ogni giorno 08:00 UTC
- Trova `CheckinFormAssignment` con form attivo e frequenza corrispondente al giorno odierno
- Per ogni cliente: invia push + email con deep link per aprire il questionario
- **Dipende da:** modulo Forms/Checkins (vedi sotto)

---

## Modulo: Forms / Checkins *(futuro)*

**Path:** `app/Modules/Forms/`

Gestisce i questionari di check-in periodici assegnati dal coach ai clienti.

### Modelli indicativi
- `CheckinForm`: id, coach_id, name, fields (json), is_active
- `CheckinFormAssignment`: id, form_id, client_id, frequency (enum: daily/weekly/monthly), next_due_at, created_at
- `CheckinResponse`: id, assignment_id, client_id, answers (json), submitted_at

### Integrazione con Notifications
Il job `sendCheckinReminders` (modulo Notifications) legge `CheckinFormAssignment` da questo modulo.

---

## Modulo: Auth — endpoint aggiuntivo

> Non un nuovo modulo, ma un endpoint mancante nel modulo Auth esistente (`app/Modules/Auth/`).

### `POST /auth/accept-invite/:token` — Onboarding cliente da link invito

**Stato:** ❌ Mancante

**Logica:**
1. Valida token JWT (7 gg TTL) generato da `POST /coaches/me/clients` quando l'email non esiste
2. Body: `{ password, firstName, lastName, dateOfBirth, gender, heightCm, objective, fitnessLevel }`
3. Completa il profilo utente (isVerified=true, imposta password)
4. Crea il record `Client` collegato al coach del token
5. Ritorna `access_token` + `refresh_token` (login diretto)

**Note:** Il token JWT deve codificare: `coach_id`, `email`, `exp`. La generazione del token avviene in `POST /coaches/me/clients` (modulo Users) quando l'email non è nel sistema.
