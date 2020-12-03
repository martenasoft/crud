<?php

namespace MartenaSoft\Crud\Event;

class CrudBeforeDeleteEvent extends AbstractCrudDeleteEvent
{
    public static function getEventName(): string
    {
        return 'crud.before.delete.event';
    }
}
