<?php

namespace App\Traits;

use DateTimeInterface;

trait SerializesDatesAsLocal
{
    /**
     * Prepare a date for array / JSON serialization.
     *
     * This ensures that dates are serialized with timezone information (ISO 8601),
     * allowing the browser to correctly interpret them in the user's local time zone.
     *
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format(DateTimeInterface::ATOM);
    }
}