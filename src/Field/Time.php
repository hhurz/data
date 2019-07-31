<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Time field type. Think of it as field type "time" in past.
 */
class Time extends DateTime
{
    /** @var string Field type for backward compatibility. */
    public $type = 'time';

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function normalize($value)
    {
        $value = parent::normalize($value);

        if ($value !== null) {
            // remove date portion from date type value
            // need 1970 in place of 0 - DB
            $value->setDate(1970, 1, 1);
        }

        return $value;
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     *
     * @return string
     */
    public function toString($value = null)
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        return $v->format('H:i:s');
    }
}
