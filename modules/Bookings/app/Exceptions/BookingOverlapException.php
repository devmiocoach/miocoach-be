<?php

namespace Modules\Bookings\Exceptions;

use Exception;

class BookingOverlapException extends Exception
{
    public function __construct()
    {
        parent::__construct('Il coach ha già una prenotazione in questo orario.', 422);
    }
}
