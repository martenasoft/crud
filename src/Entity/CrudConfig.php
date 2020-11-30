<?php

namespace MartenaSoft\Crud\Entity;

use  Doctrine\ORM\Mapping as ORM;
use MartenaSoft\Common\Library\CommonValues;
use Symfony\Component\Validator\Constraint as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use MartenaSoft\Crud\Repository\CrudConfigRepository;

/**
 * @ORM\Entity(repositoryClass="CrudConfigRepository")
 * @UniqueEntity (
 *     fields={"name"}
 * )
 */
class CrudConfig
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */

    private ?int $id = null;

}