<?php


namespace App\message;

use App\Entity\Conference;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Symfony\Component\String\Slugger\SluggerInterface;

class CommentMessage
{
    private $id;
    private $context;

    public function __construct(int $id, array $context = [])
    {
        $this->id = $id;
        $this->context = $context;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}