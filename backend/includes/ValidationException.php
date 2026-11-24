<?php

class ValidationException extends AppException
{
    private array $errors;

    public function __construct(array $errors, string $message = 'Please correct the highlighted fields.')
    {
        parent::__construct($message, 422);
        $this->errors = $errors;
    }

    public function errors(): array
    {
        return $this->errors;
    }
}
