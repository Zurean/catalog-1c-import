<?php

namespace App\Document;

use App\Document\Product\BalanceItem;
use App\Document\Product\DiscountItem;
use App\Document\Product\PriceItem;
use App\Document\Product\UnitItem;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use App\Repository\ProductRepository;
use App\Document\Product\SetItem;
use DateTimeInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @MongoDB\Document(repositoryClass=ProductRepository::class)
 * @MongoDB\Index(
 *     keys={"name"="text", "sectionName"="text", "productCode"="text", "tnved"="text", "tru"="text", "barcodes"="text"}
 * )
 * @todo Если товары понадобится удалять, то нужно обрабатывать это событие,
 * чтобы уменьшать рассчитанное количество товаров в категории
 */
class Product
{
    public const BADGE_POPULAR = 'Хит';
    public const BADGE_NEW = 'Новинка';
    public const BADGE_NEW_CODE = 'novinka';
    public const BADGE_ACTION = 'Акция';
    public const BADGE_SAMPLE = 'Витринный образец';
    public const BADGE_LIQUIDATION = 'Распродажа';
    public const BADGE_LIQUIDATION_CODE = 'rasprodazha';

    public const BADGES_PROCESS_MATCHING = [
        'акция'             => self::BADGE_ACTION,
        'новинки'           => self::BADGE_NEW,
        'новинка'           => self::BADGE_NEW,
        '_новинки'          => self::BADGE_NEW,
        'витринный образец' => self::BADGE_SAMPLE,
        'распродажа'        => self::BADGE_LIQUIDATION,
    ];

    /**
     * Статусы товара:
     * - доступен для покупки
     * - доступен для заказа
     * - ожидает поступления
     */
    public const REGULAR_OR_ON_ORDER_WEIGHT = 1;
    public const AWAITING_RECEIPT_WEIGHT = 0;

    public const CACHE_MODEL_NAME = 'product';

    /**
     * @Groups({"full-article"})
     *
     * @MongoDB\Id(strategy="UUID", type="string")
     */
    protected string $id;

    /**
     * @MongoDB\Field(type="string")
     *
     * @Assert\NotBlank(message="Не задан внешний id товара")
     */
    protected string $externalId;

    /**
     * @Groups({"full-article"})
     *
     * @MongoDB\Field(type="string")
     *
     * @Assert\NotBlank(message="Не задан символьный код товара")
     */
    protected string $code;

    /**
     * @MongoDB\Field(type="string", nullable=true)
     */
    protected ?string $vendorCode = null;

    /**
     * @Groups({"full-article"})
     *
     * @MongoDB\Field(type="string")
     *
     * @Assert\NotBlank(message="Не задано название товара")
     */
    protected string $name;

    /**
     * @MongoDB\Field(type="string")
     */
    protected string $sortableName = '';

    /**
     * @MongoDB\Field(type="string")
     */
    protected ?string $shortName = null;

    /**
     * @MongoDB\Field(type="string", nullable=true)
     */
    protected ?string $description = null;

    /**
     * @MongoDB\ReferenceOne(targetDocument=Section::class)
     */
    protected ?Section $section = null;

    /**
     * @MongoDB\Field(type="string")
     */
    protected string $sectionName = '';

    /**
     * Название бренда. Поле для поиска
     *
     * @MongoDB\Field(type="string")
     */
    protected string $brandName = '';

    /**
     * @MongoDB\EmbedMany(targetDocument=BalanceItem::class)
     */
    protected Collection $balance;

    /**
     * @MongoDB\EmbedMany(targetDocument=PriceItem::class)
     */
    protected Collection $price;

    /**
     * @MongoDB\EmbedMany(targetDocument=DiscountItem::class)
     */
    protected Collection $discounts;

    /**
     * @MongoDB\EmbedMany(targetDocument=UnitItem::class)
     */
    protected Collection $units;

    /**
     * @MongoDB\Field(type="collection")
     *  */
    protected array $badges = [];

    /**
     * @MongoDB\Field(type="collection")
     */
    protected array $filters = [];

    /**
     * The product is not available for purchase in the store, only on order.
     *
     * @MongoDB\Field(type="bool")
     */
    protected bool $onOrder = false;

    /**
     * Date of arrival product.
     *
     * @MongoDB\Field(type="date")
     */
    protected ?DateTimeInterface $dateReceipt = null;

    /**
     * Number of days it takes on delivery.
     *
     * @MongoDB\Field(type="string")
     */
    protected string $daysOrder;

    /**
     * @MongoDB\ReferenceOne(targetDocument=Brand::class, nullable=true);
     */
    protected ?Brand $brand = null;

    /**
     * @MongoDB\Field(type="string")
     *
     * @Assert\NotBlank(message="Не задан код товара")
     */
    protected string $productCode;

    /**
     * @MongoDB\Field(type="string", nullable=true)
     */
    protected ?string $tnved = null;

    /**
     * @MongoDB\Field(type="string", nullable=true)
     */
    protected ?string $tru = null;

    /**
     * @MongoDB\Field(type="collection")
     *  */
    protected array $barcodes = [];

    /**
     * @MongoDB\Field(type="hash")
     */
    protected array $characteristics = [];

    /**
     * @MongoDB\Field(type="string", nullable=true)
     */
    protected ?string $offerId = null;

    /**
     * @MongoDB\Field(type="collection")
     */
    protected array $unifyingProperties = [];

    /**
     * @MongoDB\Field(type="float", nullable=true)
     */
    protected ?float $limit = null;

    /**
     * @MongoDB\EmbedMany(targetDocument=SetItem::class)
     */
    protected Collection $set;

    /**
     * @MongoDB\Field(type="collection")
     */
    protected array $related = [];

    /**
     * @MongoDB\Field(type="collection")
     */
    protected array $additional = [];

    /**
     * @MongoDB\Field(type="float")
     *
     * @deprecated
     */
    protected float $pointsMultiplier = 1;

    /**
     * @MongoDB\Field(type="hash")
     */
    protected array $pointsMultipliers = [];

    /**
     * @MongoDB\Field(type="float")
     */
    protected float $additionalPoints = 0;

    /**
     * @MongoDB\Field(type="string", nullable=true)
     */
    protected ?string $searchSynonyms = null;

    /**
     * @MongoDB\Field(type="bool")
     */
    protected bool $active = false;

    /**
     * @MongoDB\Field(type="bool")
     */
    protected bool $isDimensional = false;

    /**
     * @MongoDB\Field(type="date")
     */
    protected ?DateTimeInterface $updatedAt = null;

    /**
     * @MongoDB\Field(notSaved=true)
     */
    protected float $score;

    /**
     * @MongoDB\Field(type="bool", nullable=true)
     */
    protected ?bool $hideScore = null;

    /**
     * @MongoDB\Field(type="int")
     *
     * @todo По умолчанию 0 не стоит из-за бага:
     * https://github.com/doctrine/mongodb-odm/issues/2349
     */
    protected int $views;

    /**
     * Если товар доступен для заказа или покупки, устанавливается статус - 1
     * Если товар "Ожидает поступления", то устанавливается статус - 0
     *
     * @MongoDB\Field(type="int")
     *
     * @todo По умолчанию 0 не стоит из-за бага:
     * https://github.com/doctrine/mongodb-odm/issues/2349
     */
    protected int $status;

    public function __construct()
    {
        $this->balance = new ArrayCollection();
        $this->price = new ArrayCollection();
        $this->discounts = new ArrayCollection();
        $this->units = new ArrayCollection();
        $this->set = new ArrayCollection();
        $this->characteristics = [];
    }

    /**
     * Строит тег для товара в кеше
     *
     * @param string $id
     *
     * @return string
     */
    public static function getCacheTag(string $id): string
    {
        return 'product_' . $id;
    }

    /**
     * @param string $brandName
     *
     * @return self
     */
    public function setBrandName(string $brandName): self
    {
        $this->brandName = $brandName;

        return $this;
    }

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
    public function getExternalId(): string
    {
        return $this->externalId;
    }

    /**
     * @param string $externalId
     *
     * @return Product
     */
    public function setExternalId(string $externalId): Product
    {
        $this->externalId = $externalId;

        return $this;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     *
     * @return Product
     */
    public function setCode(string $code): Product
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getVendorCode(): ?string
    {
        return $this->vendorCode;
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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return Product
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getSortableName(): string
    {
        return $this->sortableName;
    }

    /**
     * @param string $sortableName
     *
     * @return $this
     */
    public function setSortableName(string $sortableName): self
    {
        $this->sortableName = $sortableName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    /**
     * @param string $shortName
     *
     * @return Product
     */
    public function setShortName(string $shortName): Product
    {
        $this->shortName = $shortName;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
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
     * @return Section
     */
    public function getSection(): Section
    {
        return $this->section;
    }

    /**
     * @param Section $section
     *
     * @return Product
     */
    public function setSection(Section $section): Product
    {
        if ($this->section !== null) {
            $this->section->decCount();
        }

        $this->section = $section;

        $this->section->incCount();

        $this->sectionName = $section->getName();

        return $this;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getBalance()
    {
        return $this->balance;
    }

    public function addBalance(BalanceItem $item): self
    {
        if (!$this->balance->contains($item)) {
            $this->balance[] = $item;
        }

        return $this;
    }

    public function removeBalance(BalanceItem $item): self
    {
        if ($this->balance->contains($item)) {
            $this->balance->removeElement($item);
        }

        return $this;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param PriceItem $item
     *
     * @return $this
     */
    public function addPrice(PriceItem $item): self
    {
        if (!$this->price->contains($item)) {
            $this->price[] = $item;
        }

        return $this;
    }

    public function removePrice(PriceItem $item): self
    {
        if ($this->price->contains($item)) {
            $this->price->removeElement($item);
        }

        return $this;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getDiscounts()
    {
        return $this->discounts;
    }

    public function addDiscount(DiscountItem $item): self
    {
        if (!$this->discounts->contains($item)) {
            $this->discounts[] = $item;
        }

        return $this;
    }

    public function removeDiscount(DiscountItem $item): self
    {
        if ($this->discounts->contains($item)) {
            $this->discounts->removeElement($item);
        }

        return $this;
    }

    /**
     * @return ArrayCollection|Collection
     */
    public function getUnits(): Collection
    {
        return $this->units;
    }

    /**
     * @param UnitItem $item
     *
     * @return $this
     */
    public function addUnits(UnitItem $item): self
    {
        if (!$this->units->contains($item)) {
            $this->units[] = $item;
        }

        return $this;
    }

    /**
     * @param UnitItem $item
     *
     * @return $this
     */
    public function removeUnits(UnitItem $item): self
    {
        if ($this->units->contains($item)) {
            $this->units->removeElement($item);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getBadges(): array
    {
        return $this->badges;
    }

    /**
     * @param array $badges
     *
     * @return Product
     */
    public function setBadges(array $badges): self
    {
        $this->badges = $badges;

        return $this;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function setFilters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return bool
     */
    public function isOnOrder(): bool
    {
        return $this->onOrder;
    }

    /**
     * @param bool $onOrder
     *
     * @return self
     */
    public function setOnOrder(bool $onOrder): self
    {
        $this->onOrder = $onOrder;

        return $this;
    }

    /**
     * @return DateTimeInterface|null
     */
    public function getDateReceipt(): ?DateTimeInterface
    {
        return $this->dateReceipt;
    }

    /**
     * @param DateTimeInterface|null $dateReceipt
     *
     * @return self
     */
    public function setDateReceipt(?DateTimeInterface $dateReceipt): self
    {
        $this->dateReceipt = $dateReceipt;

        return $this;
    }

    /**
     * @return string
     */
    public function getDaysOrder(): string
    {
        return $this->daysOrder;
    }

    /**
     * @param string $daysOrder
     *
     * @return self
     */
    public function setDaysOrder(string $daysOrder): self
    {
        $this->daysOrder = $daysOrder;

        return $this;
    }

    /**
     * @return Brand|null
     */
    public function getBrand(): ?Brand
    {
        return $this->brand;
    }

    /**
     * @param Brand|null $brand
     *
     * @return self
     */
    public function setBrand(?Brand $brand): self
    {
        $this->brand = $brand;
        $this->brandName = $brand ? $brand->getName() : '';

        return $this;
    }

    /**
     * @return string
     */
    public function getProductCode(): string
    {
        return $this->productCode;
    }

    /**
     * @param string $productCode
     *
     * @return self
     */
    public function setProductCode(string $productCode): self
    {
        $this->productCode = $productCode;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTnved(): ?string
    {
        return $this->tnved;
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
     * @return string|null
     */
    public function getTru(): ?string
    {
        return $this->tru;
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
     * @return array
     */
    public function getBarcodes(): array
    {
        return $this->barcodes;
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
     * @return array
     */
    public function getCharacteristics(): array
    {
        return $this->characteristics;
    }

    /**
     * @param array $characteristics
     *
     * @return self
     */
    public function setCharacteristics(array $characteristics): self
    {
        $this->characteristics = $characteristics;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getOfferId(): ?string
    {
        return $this->offerId;
    }

    /**
     * @param string|null $offerId
     *
     * @return self
     */
    public function setOfferId(?string $offerId): self
    {
        $this->offerId = $offerId;

        return $this;
    }

    /**
     * @return array
     */
    public function getUnifyingProperties(): array
    {
        return $this->unifyingProperties;
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
     * @return float|null
     */
    public function getLimit(): ?float
    {
        return $this->limit;
    }

    /**
     * @param float|null $limit
     *
     * @return self
     */
    public function setLimit(?float $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getSet(): Collection
    {
        return $this->set;
    }

    /**
     * @param SetItem $item
     *
     * @return $this
     */
    public function addSet(SetItem $item): self
    {
        if (!$this->set->contains($item)) {
            $this->set[] = $item;
        }

        return $this;
    }

    /**
     * @param SetItem $item
     *
     * @return $this
     */
    public function removeSet(SetItem $item): self
    {
        if ($this->set->contains($item)) {
            $this->set->removeElement($item);
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getRelated(): array
    {
        return $this->related;
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
     * @return array
     */
    public function getAdditional(): array
    {
        return $this->additional;
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
     * @return float
     *
     * @deprecated
     */
    public function getPointsMultiplier(): float
    {
        return $this->pointsMultiplier;
    }

    /**
     * @param float $pointsMultiplier
     *
     * @return Product
     *
     * @deprecated
     */
    public function setPointsMultiplier(float $pointsMultiplier): self
    {
        $this->pointsMultiplier = $pointsMultiplier;

        return $this;
    }

    /**
     * @return array
     */
    public function getPointsMultipliers(): array
    {
        return $this->pointsMultipliers;
    }

    /**
     * @param array $pointsMultipliers
     *
     * @return self
     */
    public function setPointsMultipliers(array $pointsMultipliers): self
    {
        $this->pointsMultipliers = $pointsMultipliers;

        return $this;
    }

    /**
     * @return float
     */
    public function getAdditionalPoints(): float
    {
        return $this->additionalPoints;
    }

    /**
     * @param float $additionalPoints
     *
     * @return Product
     */
    public function setAdditionalPoints(float $additionalPoints): self
    {
        $this->additionalPoints = $additionalPoints;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getSearchSynonyms(): ?string
    {
        return $this->searchSynonyms;
    }

    /**
     * @param string|null $searchSynonyms
     * @return self
     */
    public function setSearchSynonyms(?string $searchSynonyms): self
    {
        $this->searchSynonyms = $searchSynonyms;

        return $this;
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param bool $active
     * @return self
     */
    public function setActive(bool $active): self
    {
        if ($this->active !== $active) {
            if ($this->active) {
                if ($this->section !== null) {
                    $this->section->decCount();
                }
            } else {
                if ($this->section !== null) {
                    $this->section->incCount();
                }
            }
        }

        $this->active = $active;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDimensional(): bool
    {
        return $this->isDimensional;
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
     * @return DateTimeInterface|null
     */
    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @return $this
     */
    public function setUpdatedAt(): self
    {
        $this->updatedAt = new DateTime();

        return $this;
    }

    /**
     * @return float
     */
    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * @param float $score
     *
     * @return self
     */
    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }

    /**
     * @return string
     */
    public function getSectionName(): string
    {
        return $this->sectionName;
    }

    /**
     * @param string $sectionName
     *
     * @return self
     */
    public function setSectionName(string $sectionName): self
    {
        $this->sectionName = $sectionName;

        return $this;
    }

    /**
     * @return bool|null
     */
    public function getHideScore(): ?bool
    {
        return $this->hideScore;
    }

    /**
     * @param bool $hideScore
     *
     * @return self
     */
    public function setHideScore(bool $hideScore): self
    {
        $this->hideScore = $hideScore;

        return $this;
    }

    /**
     * @return int
     */
    public function getViews(): int
    {
        return $this->views;
    }

    /**
     * @param int $views
     *
     * @return self
     */
    public function setViews(int $views): self
    {
        $this->views = $views;

        return $this;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     *
     * @return $this
     */
    public function setStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }
}
