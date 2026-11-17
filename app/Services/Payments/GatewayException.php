<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * Something went wrong talking to a payment provider, or a payment request is not allowed.
 * The message is ALWAYS safe to show to staff: it never contains a key, a secret or card data.
 */
class GatewayException extends RuntimeException
{
}
