<?php

namespace App\Services\Payments;

/** A hosted payment page the customer can open: the provider's session id and its URL. */
final class PaymentSession
{
    public function __construct(public readonly string $id, public readonly string $url)
    {
    }
}
