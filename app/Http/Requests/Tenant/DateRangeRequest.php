<?php

namespace App\Http\Requests\Tenant;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A list page that can be narrowed by a `from` and a `to` day (`Y-m-d`, as the date inputs send them). A value that is
 * not a date is left out as if it was not sent: the address is typed by hand now and then, and it must not end in an
 * error page.
 */
class DateRangeRequest extends FormRequest
{
    private const DATE_FORMAT = 'Y-m-d';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function fromDate(): ?CarbonImmutable
    {
        return $this->dateOrNull('from');
    }

    public function toDate(): ?CarbonImmutable
    {
        return $this->dateOrNull('to');
    }

    private function dateOrNull(string $key): ?CarbonImmutable
    {
        $value = $this->query($key);

        if (! is_string($value) || ! CarbonImmutable::hasFormat($value, self::DATE_FORMAT)) {
            return null;
        }

        return CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, $value);
    }
}
