<?php

namespace MartenaSoft\Crud\Event;

use MartenaSoft\Common\Entity\CommonEntityInterface;
use MartenaSoft\Common\Event\CommonEventInterface;
use MartenaSoft\Common\Event\CommonEventResponseInterface;
use MartenaSoft\Common\Event\CommonFormEventEntityInterface;
use MartenaSoft\Common\Event\CommonFormEventInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

abstract class AbstractCrudDeleteEvent extends AbstractCrudEvent
{
    private bool $isSafeDelete;

    public function __construct(CommonEntityInterface $entity, bool $isSafeDelete = false)
    {
        $this->entity = $entity;
        $this->isSafeDelete = $isSafeDelete;
    }
}
