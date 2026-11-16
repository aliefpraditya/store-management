<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\SaleException;
use Brick\Math\BigDecimal;

/**
 * Divides what a customer handed over at the till between the receipt and the deposit of a customer order made in the
 * same checkout. The payments are one tender: they must cover the receipt and the deposit together, and any change comes
 * out of the cash. The receipt is covered first, in the order the payments were entered, then the deposit.
 *
 * The change stays on the receipt, as the extra cash it was handed, so the receipt reads as it always does (cash given,
 * change returned) and the drawer ends up with exactly the cash that was kept. With no receipt (everything is an order)
 * the change simply is not given back from anything and only the deposit is booked.
 *
 * @phpstan-import-type SalePaymentInput from SaleService
 *
 * @phpstan-type TenderSplit array{sale: list<SalePaymentInput>, deposit: list<SalePaymentInput>}
 */
class TenderSplitter
{
    /**
     * @param  list<SalePaymentInput>  $payments
     * @return TenderSplit
     *
     * @throws SaleException
     */
    public function split(array $payments, BigDecimal $saleTotal, BigDecimal $deposit): array
    {
        $amountsPaid = array_map(fn (array $payment): BigDecimal => BigDecimal::of((string) $payment['amount']), $payments);
        $paidTotal = array_reduce($amountsPaid, fn (BigDecimal $sum, BigDecimal $amount): BigDecimal => $sum->plus($amount), BigDecimal::zero());
        $dueTotal = $saleTotal->plus($deposit);

        if (collect($payments)->contains(fn (array $payment): bool => $payment['method'] === PaymentMethod::Cod->value)) {
            throw SaleException::codWithOrderDeposit();
        }

        if ($paidTotal->isLessThan($dueTotal)) {
            throw SaleException::paymentTooLow($dueTotal);
        }

        $change = $paidTotal->minus($dueTotal);
        $cashPaid = collect($payments)->filter(fn (array $payment): bool => $payment['method'] === PaymentMethod::Cash->value)->keys()
            ->reduce(fn (BigDecimal $sum, int $index): BigDecimal => $sum->plus($amountsPaid[$index]), BigDecimal::zero());

        if ($change->isGreaterThan($cashPaid)) {
            throw SaleException::changeExceedsCash();
        }

        $amountsKept = $this->takeChangeOutOfCash($payments, $amountsPaid, $change);

        return $this->allocate($payments, $amountsKept, $saleTotal, $deposit, $change);
    }

    /**
     * The amount of each payment that stays in the till: the change is taken from the cash payments, last one first.
     *
     * @param  list<SalePaymentInput>  $payments
     * @param  list<BigDecimal>  $amountsPaid
     * @return list<BigDecimal>
     */
    private function takeChangeOutOfCash(array $payments, array $amountsPaid, BigDecimal $change): array
    {
        $amountsKept = $amountsPaid;

        for ($index = count($payments) - 1; $index >= 0 && $change->isPositive(); $index--) {
            if ($payments[$index]['method'] !== PaymentMethod::Cash->value) {
                continue;
            }

            $taken = BigDecimal::min($amountsKept[$index], $change);
            $amountsKept[$index] = $amountsKept[$index]->minus($taken);
            $change = $change->minus($taken);
        }

        return $amountsKept;
    }

    /**
     * @param  list<SalePaymentInput>  $payments
     * @param  list<BigDecimal>  $amountsKept
     * @return TenderSplit
     */
    private function allocate(array $payments, array $amountsKept, BigDecimal $saleTotal, BigDecimal $deposit, BigDecimal $change): array
    {
        $salePayments = [];
        $depositPayments = [];
        $saleLeft = $saleTotal;
        $depositLeft = $deposit;

        foreach ($payments as $index => $payment) {
            $amountLeft = $amountsKept[$index];
            $toSale = BigDecimal::min($amountLeft, $saleLeft);
            $toDeposit = BigDecimal::min($amountLeft->minus($toSale), $depositLeft);

            if ($toSale->isPositive()) {
                $salePayments[] = $this->row($payment, $toSale);
            }

            if ($toDeposit->isPositive()) {
                $depositPayments[] = $this->row($payment, $toDeposit);
            }

            $saleLeft = $saleLeft->minus($toSale);
            $depositLeft = $depositLeft->minus($toDeposit);
        }

        if ($change->isPositive() && $saleTotal->isPositive()) {
            $salePayments[] = ['method' => PaymentMethod::Cash->value, 'amount' => (string) $change->toScale(2)];
        }

        return ['sale' => $salePayments, 'deposit' => $depositPayments];
    }

    /**
     * @param  SalePaymentInput  $payment
     * @return SalePaymentInput
     */
    private function row(array $payment, BigDecimal $amount): array
    {
        return ['method' => $payment['method'], 'amount' => (string) $amount->toScale(2), 'reference' => $payment['reference'] ?? null];
    }
}
