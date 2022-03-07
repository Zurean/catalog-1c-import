<?php

namespace App\Service\Integration\Import1C\Catalog\Product\Model;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ProductImportModel
 *
 * @package App\Service\Integration\Import1C\Catalog\Model
 */
class ProductImportModel
{
    /**
     * @var string
     *
     * @Assert\NotBlank(message="Не передан id")
     */
    private string $id;

    /**
     * @var string
     *
     * @Assert\NotBlank(message="Не передан name")
     */
    private string $name;

    /**
     * @var int
     *
     * @Assert\NotBlank(message="Не передан code")
     */
    private int $code;

    /**
     * @var string|null
     */
    private ?string $vendorCode = null;

    /**
     * @var string|null
     */
    private ?string $description = null;

    /**
     * @var string
     *
     * @Assert\NotBlank(message="Не передан section")
     */
    private string $section;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан discounts")
     */
    private array $discounts;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан price")
     */
    private array $price;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан units")
     */
    private array $units;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан balance")
     */
    private array $balance;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан set")
     */
    private array $set;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан properties")
     */
    private array $properties;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан unifyingProperties")
     */
    private array $unifyingProperties;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан badge")
     */
    private array $badge;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан onOrder")
     */
    private array $onOrder;

    /**
     * @var string|null
     */
    private ?string $tnved = null;

    /**
     * @var string|null
     */
    private ?string $tru = null;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан barcodes")
     */
    private array $barcodes;

    /**
     * @var int
     *
     * @Assert\NotBlank(message="Не передан limit")
     */
    private int $limit;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан related")
     */
    private array $related;

    /**
     * @var array
     *
     * @Assert\NotNull(message="Не передан additional")
     */
    private array $additional;

    /**
     * @var array
     */
    private array $pointsMultiplier = [];

    /**
     * @var string|null
     */
    private ?string $additionalPoints = null;

    /**
     * @var bool
     *
     * @Assert\NotNull(message="Не передан isDimensional")
     */
    private bool $isDimensional = false;

    /**
     * @var string
     *
     * @Assert\NotNull(message="Не передан search")
     */
    private string $search;

    /**
     * @var bool
     *
     * @Assert\NotNull(message="Не передан visible")
     */
    private bool $visible = true;

    /**
     * @var int|null
     */
    private ?int $status = null;

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getSection(): string
    {
        return $this->section;
    }

    /**
     * @return array
     */
    public function getDiscounts(): array
    {
        return $this->discounts;
    }

    /**
     * @return array
     */
    public function getPrice(): array
    {
        return $this->price;
    }

    /**
     * @return array
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    /**
     * @return array
     */
    public function getBalance(): array
    {
        return $this->balance;
    }

    /**
     * @return array
     */
    public function getSet(): array
    {
        return $this->set;
    }

    /**
     * @return array
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return array
     */
    public function getUnifyingProperties(): array
    {
        return $this->unifyingProperties;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string|null
     */
    public function getVendorCode(): ?string
    {
        return $this->vendorCode;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array
     */
    public function getBadge(): array
    {
        return $this->badge;
    }

    /**
     * @return array
     */
    public function getOnOrder(): array
    {
        return $this->onOrder;
    }

    /**
     * @return string|null
     */
    public function getTnved(): ?string
    {
        return $this->tnved;
    }

    /**
     * @return string|null
     */
    public function getTru(): ?string
    {
        return $this->tru;
    }

    /**
     * @return array
     */
    public function getBarcodes(): array
    {
        return $this->barcodes;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @return array
     */
    public function getRelated(): array
    {
        return $this->related;
    }

    /**
     * @return array
     */
    public function getAdditional(): array
    {
        return $this->additional;
    }

    /**
     * @return array
     */
    public function getPointsMultiplier(): array
    {
        return $this->pointsMultiplier;
    }

    /**
     * @return string|null
     */
    public function getAdditionalPoints(): ?string
    {
        return $this->additionalPoints;
    }

    /**
     * @return bool
     */
    public function isDimensional(): bool
    {
        return $this->isDimensional;
    }

    /**
     * @return string
     */
    public function getSearch(): string
    {
        return $this->search;
    }

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @return int|null
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @param string $id
     *
     * @return self
     */
    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param int $code
     *
     * @return self
     */
    public function setCode(int $code): self
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @param string|null $vendorCode
     *
     * @return self
     */
    public function setVendorCode(?string $vendorCode): self
    {
        $this->vendorCode = $vendorCode;

        return $this;
    }

    /**
     * @param string|null $description
     *
     * @return self
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param string $section
     *
     * @return self
     */
    public function setSection(string $section): self
    {
        $this->section = $section;

        return $this;
    }

    /**
     * @param array $discounts
     *
     * @return self
     */
    public function setDiscounts(array $discounts): self
    {
        $this->discounts = $discounts;

        return $this;
    }

    /**
     * @param array $price
     *
     * @return self
     */
    public function setPrice(array $price): self
    {
        $this->price = $price;

        return $this;
    }

    /**
     * @param array $units
     *
     * @return self
     */
    public function setUnits(array $units): self
    {
        $this->units = $units;

        return $this;
    }

    /**
     * @param array $balance
     *
     * @return self
     */
    public function setBalance(array $balance): self
    {
        $this->balance = $balance;

        return $this;
    }

    /**
     * @param array $set
     *
     * @return self
     */
    public function setSet(array $set): self
    {
        $this->set = $set;

        return $this;
    }

    /**
     * @param array $properties
     *
     * @return self
     */
    public function setProperties(array $properties): self
    {
        $this->properties = $properties;

        return $this;
    }

    /**
     * @param array $unifyingProperties
     *
     * @return self
     */
    public function setUnifyingProperties(array $unifyingProperties): self
    {
        $this->unifyingProperties = $unifyingProperties;

        return $this;
    }

    /**
     * @param array $badge
     *
     * @return self
     */
    public function setBadge(array $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * @param array $onOrder
     *
     * @return self
     */
    public function setOnOrder(array $onOrder): self
    {
        $this->onOrder = $onOrder;

        return $this;
    }

    /**
     * @param string|null $tnved
     *
     * @return self
     */
    public function setTnved(?string $tnved): self
    {
        $this->tnved = $tnved;

        return $this;
    }

    /**
     * @param string|null $tru
     *
     * @return self
     */
    public function setTru(?string $tru): self
    {
        $this->tru = $tru;

        return $this;
    }

    /**
     * @param array $barcodes
     *
     * @return self
     */
    public function setBarcodes(array $barcodes): self
    {
        $this->barcodes = $barcodes;

        return $this;
    }

    /**
     * @param int $limit
     *
     * @return self
     */
    public function setLimit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @param array $related
     *
     * @return self
     */
    public function setRelated(array $related): self
    {
        $this->related = $related;

        return $this;
    }

    /**
     * @param array $additional
     *
     * @return self
     */
    public function setAdditional(array $additional): self
    {
        $this->additional = $additional;

        return $this;
    }

    /**
     * @param array $pointsMultiplier
     *
     * @return self
     */
    public function setPointsMultiplier(array $pointsMultiplier): self
    {
        $this->pointsMultiplier = $pointsMultiplier;

        return $this;
    }

    /**
     * @param string|null $additionalPoints
     *
     * @return self
     */
    public function setAdditionalPoints(?string $additionalPoints): self
    {
        $this->additionalPoints = $additionalPoints;

        return $this;
    }

    /**
     * @param bool $isDimensional
     *
     * @return self
     */
    public function setIsDimensional(bool $isDimensional): self
    {
        $this->isDimensional = $isDimensional;

        return $this;
    }

    /**
     * @param string $search
     *
     * @return self
     */
    public function setSearch(string $search): self
    {
        $this->search = $search;

        return $this;
    }

    /**
     * @param bool $visible
     *
     * @return self
     */
    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * @param int|null $status
     *
     * @return self
     */
    public function setStatus(?int $status): self
    {
        $this->status = $status;

        return $this;
    }
}
