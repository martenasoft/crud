<?php

namespace MartenaSoft\Crud\Event;

class CrudBeforeSaveEvent extends AbstractCrudEvent
{
    public static function getEventName(): string
    {
        return 'crud.before.save.event';
    }
}