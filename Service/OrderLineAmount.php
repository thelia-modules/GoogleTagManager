<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GoogleTagManager\Service;

use Thelia\Model\ConfigQuery;
use Thelia\Model\OrderProduct;

/**
 * The amount one order line was invoiced, and the unit price to report for it.
 *
 * GA4 reads an item as a unit price times a quantity, and expects the items of a
 * purchase to add up to the value of the event. That only holds if the line total is
 * built the way the core built it when the order was placed, and the core has three
 * rules: orders placed before Thelia 2.4 were never rounded, orders invoiced before
 * the shop changed its rounding mode are frozen on the rule they were invoiced with,
 * and the rest follow order_rounding_mode (thelia/thelia#3801).
 *
 * The variables are read raw rather than through ConfigQuery::getOrderRoundingMode()
 * so the module keeps working against a core that predates that pull request:
 * order_rounding_mode is simply absent there and the historical rule applies.
 */
final class OrderLineAmount
{
    /**
     * Mirrors ConfigQuery::ROUNDING_MODE_* (thelia/thelia#3801).
     */
    private const MODE_SUM_OF_ROUNDINGS = 1;
    private const MODE_ROUNDING_OF_SUMS = 2;

    /**
     * The unit price to report for a line, taxed or not: the amount the line was
     * invoiced divided by the quantity reported alongside it, so that multiplying the
     * two back together gives the invoiced amount. Kept at the precision the prices
     * are stored with, since a price per gram does not survive the cent.
     */
    public static function unitAmount(OrderProduct $orderProduct, bool $taxed, float $reportedQuantity): float
    {
        if (0.0 === $reportedQuantity) {
            return 0.0;
        }

        return round(self::lineTotal($orderProduct, $taxed) / $reportedQuantity, 6);
    }

    /**
     * What the line was invoiced, totalled the way Order::getTotalAmount() totals it.
     */
    public static function lineTotal(OrderProduct $orderProduct, bool $taxed): float
    {
        $wasInPromo = 1 === (int) $orderProduct->getWasInPromo();
        $quantity = (float) $orderProduct->getQuantity();
        $unitPrice = (float) ($wasInPromo ? $orderProduct->getPromoPrice() : $orderProduct->getPrice());

        $mode = self::modeForOrder((int) $orderProduct->getOrderId());

        // Every tax of the line counts, not just the first one: a line under two tax
        // rules carries two rows here.
        $unitTax = 0.0;
        foreach ($orderProduct->getOrderProductTaxes() as $orderProductTax) {
            $rowTax = (float) ($wasInPromo ? $orderProductTax->getPromoAmount() : $orderProductTax->getAmount());
            $unitTax += self::MODE_SUM_OF_ROUNDINGS === $mode ? round($rowTax, 2) : $rowTax;
        }

        if (self::MODE_SUM_OF_ROUNDINGS === $mode) {
            $unitPrice = round($unitPrice, 2);
        }

        $unitAmount = $taxed ? $unitPrice + $unitTax : $unitPrice;

        return self::MODE_ROUNDING_OF_SUMS === $mode
            ? round($unitAmount * $quantity, 2)
            : $unitAmount * $quantity;
    }

    /**
     * Which of the three rules the order was invoiced with. Null stands for the
     * pre-2.4 rule, which rounds nowhere at all.
     */
    private static function modeForOrder(int $orderId): ?int
    {
        if ($orderId > 0 && $orderId <= (int) ConfigQuery::read('last_legacy_rounding_order_id', 0)) {
            return null;
        }

        $shopMode = self::MODE_ROUNDING_OF_SUMS === (int) ConfigQuery::read('order_rounding_mode', self::MODE_SUM_OF_ROUNDINGS)
            ? self::MODE_ROUNDING_OF_SUMS
            : self::MODE_SUM_OF_ROUNDINGS;

        if (self::MODE_ROUNDING_OF_SUMS === $shopMode
            && $orderId > 0
            && $orderId <= (int) ConfigQuery::read('last_sum_of_roundings_order_id', 0)) {
            return self::MODE_SUM_OF_ROUNDINGS;
        }

        return $shopMode;
    }
}
