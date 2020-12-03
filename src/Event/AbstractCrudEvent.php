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

abstract class AbstractCrudEvent extends Event implements
    CommonEventInterface,
    CommonFormEventInterface,
    CommonFormEventEntityInterface,
    CommonEventResponseInterface
{
    protected ?Response $response = null;
    protected ?string $redirectUrl = null;
    protected CommonEntityInterface $entity;
    protected FormInterface $form;

    public function __construct(FormInterface $form, CommonEntityInterface $entity)
    {
        $this->form = $form;
        $this->entity = $entity;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(?Response $response): self
    {
        $this->response = $response;
        return $this;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function setRedirectUrl(string $redirectUrl): self
    {
        $this->redirectUrl = $redirectUrl;
        return $this;
    }

    public function getEntity(): CommonEntityInterface
    {
        return $this->entity;
    }

    public function getForm(): FormInterface
    {
        return $this->form;
    }

}
