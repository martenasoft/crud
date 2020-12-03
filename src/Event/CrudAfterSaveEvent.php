<?php

namespace MartenaSoft\Crud\Event;

class CrudAfterSaveEvent extends AbstractCrudEvent
{
    public static function getEventName(): string
    {
        return 'crud.after.save.event';
    }
}
