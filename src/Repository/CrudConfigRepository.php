<?php

namespace MartenaSoft\Crud\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

use MartenaSoft\Crud\Entity\CrudConfig;

class CrudConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CrudConfig::class);
    }

}