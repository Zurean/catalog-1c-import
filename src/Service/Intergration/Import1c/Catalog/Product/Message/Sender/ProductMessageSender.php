<?php

namespace App\Service\Integration\Import1C\Catalog\Product\Message\Sender;

use App\Service\Integration\Import1C\Catalog\Product\Message\Message;
use Symfony\Component\Messenger\MessageBusInterface;

class ProductMessageSender
{
    /**
     * @var MessageBusInterface
     */
    private MessageBusInterface $bus;

    /**
     * CategoriesMessageSender constructor.
     * @param MessageBusInterface $bus
     */
    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    /**
     * @param Message $message
     */
    public function send(Message $message): void
    {
        $this->bus->dispatch($message);
    }
}
