<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class LocationNameRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $isValid = true;
        try {
            (new \League\ISO3166\ISO3166)->name($value);
        } catch (\OutOfBoundsException $exception) {
            $isValid = false;
        }
        return $isValid;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The validation error message.';
    }
}
