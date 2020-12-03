<?php

namespace MartenaSoft\Crud\Event;

class CrudAfterDeleteEvent extends AbstractCrudDeleteEvent
{
    public static function getEventName(): string
    {
        return 'crud.after.delete.event';
    }
}
