<?php

namespace MartenaSoft\Crud\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use MartenaSoft\Crud\Repository\CrudRepository;

/**
 * @ORM\Entity(repositoryClass=CrudRepository::class)
 * @UniqueEntity(
 *     fields={"name"}
 * )
 */
class Crud
{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;
    
    /** 
     * @Assert\NotBlank()
     * @@ORM\Column() 
     */
    private ?string $name;
}

