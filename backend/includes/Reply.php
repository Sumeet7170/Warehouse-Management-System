<?php

final class Reply
{
    public mixed $data;
    public ?string $message;
    public ?array $meta;

    public function __construct(mixed $data = null, ?string $message = null, ?array $meta = null)
    {
        $this->data = $data;
        $this->message = $message;
        $this->meta = $meta;
    }
}
