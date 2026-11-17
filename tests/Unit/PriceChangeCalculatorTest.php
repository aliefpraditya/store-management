<?php

namespace Tests\Unit;

use App\Enums\CostChangeMethod;
use App\Enums\SellingChangeMethod;
use App\Models\ProductUnit;
use App\Services\PriceChangeCalculator;
use Tests\TestCase;

class PriceChangeCalculatorTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function unit(string $cost = '6000.00', string $price = '10000.00', array $overrides = []): ProductUnit
    {
        return (new ProductUnit)->forceFill([
            'id' => 1, 'unit_code' => 'PCS', 'is_base' => true, 'is_sellable' => true,
            'cost_price' => $cost, 'selling_price' => $price, ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function change(array $overrides = []): array
    {
        return ['unit_code' => null, 'cost_method' => null, 'cost_value' => null, 'selling_method' => null, 'selling_value' => null, ...$overrides];
    }

    public function test_a_unit_outside_the_chosen_unit_is_left_alone(): void
    {
        $plan = (new PriceChangeCalculator)->calculate($this->unit(), $this->change(['unit_code' => ' dus ', 'selling_method' => SellingChangeMethod::Percent, 'selling_value' => '10']));

        $this->assertFalse($plan['is_changed']);
        $this->assertNull($plan['skip_reason']);
        $this->assertSame('10000.00', $plan['new_price']);
    }

    public function test_the_chosen_unit_is_matched_without_regard_to_case_and_spaces(): void
    {
        $plan = (new PriceChangeCalculator)->calculate($this->unit(), $this->change(['unit_code' => ' pcs ', 'selling_method' => SellingChangeMethod::Nominal, 'selling_value' => '500']));

        $this->assertTrue($plan['is_changed']);
        $this->assertSame('10500.00', $plan['new_price']);
    }

    public function test_percent_changes_round_half_up_to_the_sen(): void
    {
        $calculator = new PriceChangeCalculator;

        $this->assertSame('1358.01', $calculator->calculate($this->unit('1234.55'), $this->change(['cost_method' => CostChangeMethod::Percent, 'cost_value' => '10']))['new_cost']);
        $this->assertSame('1358.02', $calculator->calculate($this->unit('1234.56'), $this->change(['cost_method' => CostChangeMethod::Percent, 'cost_value' => '10']))['new_cost']);
    }

    public function test_a_margin_is_a_share_of_the_price_using_the_modal_after_the_same_change(): void
    {
        $plan = (new PriceChangeCalculator)->calculate($this->unit(), $this->change([
            'cost_method' => CostChangeMethod::Nominal, 'cost_value' => '2000', 'selling_method' => SellingChangeMethod::Margin, 'selling_value' => '25',
        ]));

        $this->assertSame('8000.00', $plan['new_cost']);
        $this->assertSame('10666.67', $plan['new_price']);
    }

    public function test_keeping_the_margin_scales_the_price_with_the_modal(): void
    {
        $plan = (new PriceChangeCalculator)->calculate($this->unit(), $this->change([
            'cost_method' => CostChangeMethod::Percent, 'cost_value' => '10', 'selling_method' => SellingChangeMethod::KeepMargin,
        ]));

        $this->assertSame('11000.00', $plan['new_price']);
    }

    public function test_a_unit_that_is_not_sold_keeps_its_price(): void
    {
        $plan = (new PriceChangeCalculator)->calculate($this->unit(overrides: ['is_sellable' => false]), $this->change(['selling_method' => SellingChangeMethod::Percent, 'selling_value' => '10']));

        $this->assertFalse($plan['is_changed']);
    }

    public function test_a_changed_unit_whose_price_ends_under_its_modal_is_flagged(): void
    {
        $plan = (new PriceChangeCalculator)->calculate($this->unit(), $this->change(['cost_method' => CostChangeMethod::Percent, 'cost_value' => '100']));

        $this->assertTrue($plan['is_changed']);
        $this->assertTrue($plan['is_below_cost']);
    }

    public function test_a_price_equal_to_the_modal_or_above_it_is_not_flagged(): void
    {
        $calculator = new PriceChangeCalculator;

        $this->assertFalse($calculator->calculate($this->unit(), $this->change(['cost_method' => CostChangeMethod::Set, 'cost_value' => '10000']))['is_below_cost']);
        $this->assertFalse($calculator->calculate($this->unit(), $this->change(['cost_method' => CostChangeMethod::Percent, 'cost_value' => '10']))['is_below_cost']);
    }

    public function test_a_unit_that_is_not_changed_or_not_sold_is_not_flagged_even_when_its_price_is_under_its_modal(): void
    {
        $calculator = new PriceChangeCalculator;
        $alreadyBelowCost = $this->unit(cost: '12000.00');

        $this->assertFalse($calculator->calculate($alreadyBelowCost, $this->change(['unit_code' => 'DUS', 'cost_method' => CostChangeMethod::Percent, 'cost_value' => '5']))['is_below_cost']);
        $this->assertFalse($calculator->calculate($this->unit(overrides: ['is_sellable' => false]), $this->change(['cost_method' => CostChangeMethod::Percent, 'cost_value' => '100']))['is_below_cost']);
    }

    public function test_keeping_the_margin_while_the_modal_rises_is_not_flagged(): void
    {
        $plan = (new PriceChangeCalculator)->calculate($this->unit(), $this->change([
            'cost_method' => CostChangeMethod::Percent, 'cost_value' => '100', 'selling_method' => SellingChangeMethod::KeepMargin,
        ]));

        $this->assertFalse($plan['is_below_cost']);
    }

    public function test_a_price_rule_that_cannot_be_worked_out_also_leaves_the_modal_and_says_so(): void
    {
        $plan = (new PriceChangeCalculator)->calculate($this->unit('0.00', '10000.00'), $this->change([
            'cost_method' => CostChangeMethod::Set, 'cost_value' => '500', 'selling_method' => SellingChangeMethod::KeepMargin,
        ]));

        $this->assertFalse($plan['is_changed']);
        $this->assertSame('0.00', $plan['new_cost']);
        $this->assertSame('Modal atau harga jual sekarang 0, margin lama tidak bisa dihitung. Modal satuan ini juga tidak diubah.', $plan['skip_reason']);
    }

    public function test_results_below_zero_or_past_the_column_are_skipped_with_a_reason(): void
    {
        $calculator = new PriceChangeCalculator;

        $this->assertSame('Modal akan jadi negatif.', $calculator->calculate($this->unit(), $this->change(['cost_method' => CostChangeMethod::Nominal, 'cost_value' => '-7000']))['skip_reason']);
        $this->assertSame('Harga jual akan jadi negatif.', $calculator->calculate($this->unit(), $this->change(['selling_method' => SellingChangeMethod::Nominal, 'selling_value' => '-20000']))['skip_reason']);
        $this->assertSame('Modal akan melebihi batas harga.', $calculator->calculate($this->unit('9999999999999.00'), $this->change(['cost_method' => CostChangeMethod::Nominal, 'cost_value' => '1']))['skip_reason']);
        $this->assertSame('Harga jual akan melebihi batas harga.', $calculator->calculate($this->unit(price: '9999999999999.99'), $this->change(['selling_method' => SellingChangeMethod::Nominal, 'selling_value' => '0.01']))['skip_reason']);
        $this->assertNull($calculator->calculate($this->unit(price: '9999999999999.00'), $this->change(['selling_method' => SellingChangeMethod::Nominal, 'selling_value' => '0.99']))['skip_reason']);
    }
}
