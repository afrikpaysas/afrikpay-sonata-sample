<?php

namespace App\Entity;

use App\Repository\SonataUserUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Sonata\UserBundle\Entity\BaseUser;

#[ORM\Entity(repositoryClass: SonataUserUserRepository::class)]
#[ORM\Table("sonata_user__user")]
class SonataUserUser extends BaseUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column("id","integer")]
    protected $id;

    public function getId(): ?int
    {
        return $this->id;
    }
}
