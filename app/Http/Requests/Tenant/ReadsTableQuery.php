<?php

namespace App\Http\Requests\Tenant;

/**
 * For a report screen whose tables are turned from the address (page, size, order): anything that is not a positive
 * whole number is the first page, and a value that is not one of the options is the first option, so a hand-typed
 * address never ends in an error page.
 */
trait ReadsTableQuery
{
    protected function positiveInteger(string $key): int
    {
        return max(filter_var($this->query($key), FILTER_VALIDATE_INT) ?: 1, 1);
    }

    /**
     * @param  list<int|string>  $options
     */
    protected function optionOrFirst(string $key, array $options): int|string
    {
        $value = $this->query($key);

        foreach ($options as $option) {
            if ((string) $option === $value) {
                return $option;
            }
        }

        return $options[0];
    }
}
