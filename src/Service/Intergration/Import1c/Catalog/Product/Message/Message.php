<?php

namespace App\Service\Integration\Import1C\Catalog\Product\Message;

class Message
{
    /**
     * @var string
     */
    private string $message;

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     *
     * @return self
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }
}
