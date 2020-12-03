<?php

namespace MartenaSoft\Crud\Event;

class CrudAfterFindEvent extends AbstractCrudEvent
{
    public static function getEventName(): string
    {
        return 'crud.after.find.event';
    }
}
