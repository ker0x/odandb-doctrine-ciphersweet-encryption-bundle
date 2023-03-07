<?php

declare(strict_types=1);

namespace Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Odandb\DoctrineCiphersweetEncryptionBundle\Attribute\EncryptedField;
use Odandb\DoctrineCiphersweetEncryptionBundle\Tests\Repository\MyEntityRepository;

#[ORM\Entity(repositoryClass: MyEntityRepository::class)]
class MyEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: Types::INTEGER)]
    protected ?int $id = null;

    #[ORM\Column(type: Types::STRING)]
    #[EncryptedField]
    private string $accountName;

    #[ORM\Column(type: Types::STRING, length: 10)]
    private string $accountNameBi;

    public function __construct(string $accountName)
    {
        $this->accountName = $accountName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountName(): string
    {
        return $this->accountName;
    }

    public function setAccountName(string $accountName): void
    {
        $this->accountName = $accountName;
    }

    public function getAccountNameBi(): string
    {
        return $this->accountNameBi;
    }

    public function setAccountNameBi(string $accountNameBi): self
    {
        $this->accountNameBi = $accountNameBi;

        return $this;
    }
}
