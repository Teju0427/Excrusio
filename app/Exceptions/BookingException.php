<?php

namespace App\Exceptions;

use RuntimeException;

/** A booking rule was broken (sold out, too late, too many guests…). The message is safe to show to the guest. */
class BookingException extends RuntimeException {}
