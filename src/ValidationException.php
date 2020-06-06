<?php

namespace atk4\data;

/**
 * Class description?
 */
class ValidationException extends Exception
{
    /** @var array Array of errors */
    public $errors = [];

    /**
     * Constructor.
     *
     * @param array $errors Array of errors
     * @param mixed $intent
     *
     * @return \Exception
     */
    public function __construct(array $errors, $model = null, $intent = null)
    {
        if (count($errors) === 0) {
            throw new Exception('Incorrect use of ValidationException, at least one error must be given');
        }

        $this->errors = $errors;

        if (count($errors) === 1) {
            parent::__construct(reset($errors));
            $this->addMoreInfo('field', key($errors));
        } else {
            parent::__construct('Multiple unhandled validation errors');
            $this->addMoreInfo('errors', $errors)
                ->addMoreInfo('intent', $intent);
        }
        $this->addMoreInfo('model', $model);
    }
}
