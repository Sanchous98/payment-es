<?php

namespace PaymentSystem\Enum;

enum SubscriptionStatusEnum: string
{
    /**
     * Subscription is waiting for payment to finalize
     */
    case PENDING = 'pending';
    /**
     * Subscription is active
     */
    case ACTIVE = 'active';
    /**
     * Subscription payment failed, but it's still in grace period
     */
    case SUSPENDED = 'suspended';
    /**
     * Subscription is canceled manually or due to failed payment
     */
    case CANCELLED = 'cancelled';
    const CANCELED = self::CANCELLED;
}
