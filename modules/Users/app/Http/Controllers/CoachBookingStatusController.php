<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingService;

class CoachBookingStatusController extends Controller
{
    public function complete(Request $request, Booking $booking, BookingService $service): RedirectResponse
    {
        $coach = $request->user()->coach;

        abort_if(! $coach || $booking->coach_id !== $coach->id, 403);
        abort_if($booking->status !== BookingStatus::Confirmed, 422, 'Only confirmed bookings can be completed.');

        $service->complete($booking);

        return back();
    }
}
