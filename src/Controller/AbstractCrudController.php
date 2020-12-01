<?php

namespace MartenaSoft\Crud\Controller;

abstract class AbstractCrudController extends AbstractCrudBaseController
{
    protected function getRepositoryClassName(): string
    {
        return $this->getNamespace().'\\Repository\\'.$this->getClassPrefix().'Repository';
    }

    protected function getEntityClassName(): string
    {
        return $this->getNamespace().'\\Entity\\'.$this->getClassPrefix();
    }

    protected function getIndexPageUrl(): string
    {
        return $this->generateUrl('admin_'. strtolower($this->getClassPrefix()).'_index');
    }

    protected function getCreateButtonUrl(): string
    {
        return $this->generateUrl('admin_'. strtolower($this->getClassPrefix()).'_create');
    }

    protected function getConfigButtonUrl(): string
    {
        return $this->generateUrl('admin_'. strtolower($this->getClassPrefix()).'_config');
    }

    protected function getFormClassName(): string
    {
        return $this->getNamespace().'\\Form\\'.$this->getClassPrefix().'FormType';
    }

    protected function getNamespace(): string
    {
        $class = new \ReflectionClass($this);
        $namespace = $class->getNamespaceName();

        return substr($namespace, 0, strrpos($namespace, '\\'));
    }

    protected function getClassPrefix(): string
    {
        $class = new \ReflectionClass($this);
        $className = $class->getShortName();
        preg_match('/[A-Z]{1}[a-z]+/',  $className, $arr);
        return (!empty($arr[0]) ? $arr[0] : $className);
    }


    protected function getRouteIndex(): string
    {
        return $this->getActionButtonRoute('index');
    }

    protected function getRouteCreate(): string
    {
        return $this->getActionButtonRoute('create');
    }

    protected function getRouteEdit(): string
    {
        return $this->getActionButtonRoute('edit');
    }

    protected function getRouteDelete(): string
    {
        return $this->getActionButtonRoute('delete');
    }

    protected function getActionButtonRoute(string $route, string $prefix = 'admin'): string
    {
        return $prefix.'_'.strtolower($this->getClassPrefix()).'_'.strtolower($route);
    }
}
