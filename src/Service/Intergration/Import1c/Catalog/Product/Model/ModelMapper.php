<?php

namespace App\Service\Integration\Import1C\Catalog\Product\Model;

use App\Document\Brand;
use App\Document\City;
use App\Document\Product;
use App\Document\Store;
use App\Repository\BrandRepository;
use App\Repository\CityRepository;
use App\Repository\Loyalty\StatusRepository;
use App\Repository\ProductPropertyRepository;
use App\Repository\ProductRepository;
use App\Repository\SectionRepository;
use App\Repository\StoreRepository;
use App\Service\Calculator\Product\BalanceCalculator;
use App\Service\Calculator\Product\LimitExceedingException;
use App\Service\Calculator\Product\UnitItemCalculator;
use App\Service\Creator\BrandCreator;
use App\Service\Mapper\City as CityMapper;
use App\Service\Order\OrderCreateException;
use App\Service\Product\ProductService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\MongoDBException;
use DomainException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use JsonException;
use RuntimeException;

class ModelMapper
{
    /**
     * @var ProductImportModel|null
     */
    private ?ProductImportModel $model;

    /**
     * @var CityMapper
     */
    private CityMapper $cityMapper;

    /**
     * @var array
     */
    private array $priceByCityConfig;

    /**
     * @var string
     */
    private string $requiredCityExternalId;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $invalidProductsLogger;

    /**
     * @var SectionRepository
     */
    private SectionRepository $sectionRepository;

    /**
     * @var ProductPropertyRepository
     */
    private ProductPropertyRepository $propertyRepository;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var BrandRepository
     */
    private BrandRepository $brandRepository;

    /**
     * @var BrandCreator
     */
    private BrandCreator $brandCreator;

    /**
     * @var ProductRepository
     */
    private ProductRepository $productRepository;

    /**
     * @var StoreRepository
     */
    private StoreRepository $storeRepository;

    /**
     * @var UnitItemCalculator
     */
    private UnitItemCalculator $unitItemCalculator;

    /**
     * @var BalanceCalculator
     */
    private BalanceCalculator $balanceCalculator;

    /**
     * @var DocumentManager
     */
    private DocumentManager $dm;

    /**
     * @var StatusRepository
     */
    private StatusRepository $statusRepository;

    /**
     * Хранилище запрошенных ранее свойств из справочника
     *
     * @var array
     */
    private array $storedFilters = [];

    /**
     * Хранилище запрошенных ранее брендов из справочника
     *
     * @var array
     */
    private array $storedBrands = [];

    /**
     * @var CityRepository
     */
    private CityRepository $cityRepository;

    /**
     * @var array
     */
    private array $priceByCityMapping;

    /**
     * @var array
     */
    private array $clubPriceByCityMapping;

    /**
     * @param CityMapper $cityMapper
     * @param ParameterBagInterface $parameterBag
     * @param LoggerInterface $invalidProductsLogger
     * @param SectionRepository $sectionRepository
     * @param ProductPropertyRepository $propertyRepository
     * @param LoggerInterface $importProductHandlerLogger
     * @param BrandRepository $brandRepository
     * @param BrandCreator $brandCreator
     * @param ProductRepository $productRepository
     * @param StoreRepository $storeRepository
     * @param UnitItemCalculator $unitItemCalculator
     * @param BalanceCalculator $balanceCalculator
     * @param DocumentManager $dm
     * @param StatusRepository $statusRepository
     * @param CityRepository $cityRepository
     *
     * @throws MongoDBException
     */
    public function __construct(
        CityMapper $cityMapper,
        ParameterBagInterface $parameterBag,
        LoggerInterface $invalidProductsLogger,
        SectionRepository $sectionRepository,
        ProductPropertyRepository $propertyRepository,
        LoggerInterface $importProductHandlerLogger,
        BrandRepository $brandRepository,
        BrandCreator $brandCreator,
        ProductRepository $productRepository,
        StoreRepository $storeRepository,
        UnitItemCalculator $unitItemCalculator,
        BalanceCalculator $balanceCalculator,
        DocumentManager $dm,
        StatusRepository $statusRepository,
        CityRepository $cityRepository
    )
    {
        $this->cityMapper = $cityMapper;
        $this->priceByCityConfig = $parameterBag->get('priceByCity');
        $this->requiredCityExternalId = $parameterBag->get('priceByCity')['Павлодар'];
        $this->invalidProductsLogger = $invalidProductsLogger;
        $this->sectionRepository = $sectionRepository;
        $this->propertyRepository = $propertyRepository;
        $this->logger = $importProductHandlerLogger;
        $this->brandRepository = $brandRepository;
        $this->brandCreator = $brandCreator;
        $this->productRepository = $productRepository;
        $this->storeRepository = $storeRepository;
        $this->unitItemCalculator = $unitItemCalculator;
        $this->balanceCalculator = $balanceCalculator;
        $this->dm = $dm;
        $this->statusRepository = $statusRepository;
        $this->cityRepository = $cityRepository;

        $this->priceByCityMapping       = $this->priceByCityMapProcessing($parameterBag->get('priceByCity'));
        $this->clubPriceByCityMapping   = $this->priceByCityMapProcessing($parameterBag->get('clubPriceByCity'));

        if (empty($parameterBag->get('priceByCity')['Павлодар'])) {
            throw new RuntimeException('Config price.yaml is missing or invalid');
        }
    }

    /**
     * @param ProductImportModel $model
     *
     * @return self
     *
     * @throws DomainException
     */
    public function loadModel(ProductImportModel $model): self
    {
        $this->model = $model;

        $section = $this->sectionRepository->findOneByExternalId($model->getSection());

        if (is_null($section)) {
            throw new DomainException(
                sprintf('Не найден раздел, переданный в модели импорта товара %s', $model->getSection())
            );
        }

        return $this;
    }

    /**
     * @throws DomainException
     */
    private function checkModelSetting(): void
    {
        if (is_null($this->model)) {
            throw new DomainException('Не передана модель импорта товара для мапинга данных');
        }
    }

    /**
     * @return string
     *
     * @throws JsonException|DomainException
     */
    public function mapData(): string
    {
        $this->checkModelSetting();

        $onOrder = $this->model->getOnOrder();

        $mappedData = [
            'name'             => trim(
                empty($this->model->getUnifyingProperties())
                    ? $this->model->getName()
                    : $this->processNameByOffer()
            ),
            'externalId'       => $this->model->getId(),
            'description'      => $this->processStringField($this->model->getDescription()),
            'badges'           => $this->processBadges($this->model->getBadge()),
            'onOrder'          => $onOrder[0]['enabled'],
            'dateReceipt'      => $onOrder[0]['supply'][0]['date'] ?? null,
            'daysOrder'        => (string)$onOrder[0]['daysCount'],
            'productCode'      => (string)$this->model->getCode(),
            'vendorCode'       => $this->processStringField($this->model->getVendorCode()),
            'tnved'            => $this->processStringField($this->model->getTnved()),
            'tru'              => $this->processStringField($this->model->getTru()),
            'barcodes'         => $this->model->getBarcodes(),
            'characteristics'  => $this->buildCharacteristic(),
            'limit'            => $this->model->getLimit(),
            'related'          => $this->wrappedIdMapper($this->model->getRelated()),
            'additional'       => $this->wrappedIdMapper($this->model->getAdditional()),
            'pointsMultiplier' => $this->getPointsMultiplier(),
            'pointsMultipliers'=> $this->getPointsMultipliers(),
            'additionalPoints' => (float)($this->model->getAdditionalPoints() ?? 0),
            'isDimensional'    => $this->model->isDimensional() ?? false,
            'searchSynonyms'   => $this->model->getSearch() ?? '',
            'active'           => $this->model->isVisible(),
            'status'           => $this->model->getStatus()
        ];

        $mappedData['sortableName'] = ProductService::getSortableName($mappedData['name']);

        return json_encode($mappedData, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array
     *
     * @throws MongoDBException|DomainException
     */
    public function mapDiscounts(): array
    {
        $this->checkModelSetting();

        $result = [];

        $priceByCityMapping = $this->cityMapper->mapById($this->priceByCityConfig);

        foreach ($this->model->getDiscounts() as $discount) {
            // На клубные цены скидки не распространяются, поэтому здесь их не учитываем
            if (isset($this->priceByCityMapping[$discount['type']])) {
                $result[] = [
                    'city'     => $priceByCityMapping[$discount['type']],
                    'type'     => $discount['type'],
                    'value'    => $discount['value'],
                    'maxValue' => $discount['maxValue'],
                ];
            }
        }

        return $result;
    }

    /**
     * @param array $price
     * @param City $city
     * @param array $discounts
     *
     * @return float
     */
    private function mapDiscountedPrices(array $price, City $city, array $discounts = []): float
    {
        $amount = (float)$price['value'];

        if (empty($discounts)) {
            return $amount;
        }

        foreach ($discounts as $discount) {
            if ($price['type'] === $discount['type'] && $city->getId() === $discount['city']->getId()) {
                $discountValue = (float)$discount['value'];

                return $amount - $amount * $discountValue / 100;
            }
        }

        return $amount;
    }

    /**
     * @return array|array[]
     *
     */
    public function mapPrices(): array
    {
        $this->checkModelSetting();

        $result = [
            'prices'     => [],
            'clubPrices' => [],
        ];

        $prices = $this->model->getPrice();
        $discounts = $this->model->getDiscounts();

        $isValid = false;
        array_walk(
            $prices,
            function ($price) use (&$isValid) {
                if (
                    !empty($price['type']) &&
                    $price['type'] === $this->requiredCityExternalId
                ) {
                    $isValid = true;
                }
            }
        );

        if (!$isValid) {
            $this->invalidProductsLogger->warning(
                'IMPORT: Получен невалидный товар (цена для г. Павлодар отсутствует)',
                [
                    'externalId' => $this->model->getId()
                ]
            );

            throw new OrderCreateException(
                sprintf('Попытка загрузить товар "%s" с нулевой ценой', $this->model->getId())
            );
        }

        foreach ($prices as $price) {
            $city = $this->priceByCityMapping[$price['type']] ?? null;

            if ($city !== null) {
                $result['prices'][] = [
                    'type'            => $price['type'],
                    'value'           => $price['value'],
                    'discountedPrice' => $this->mapDiscountedPrices($price, $city, $discounts),
                    'city'            => $city,
                ];
            }

            $city = $this->clubPriceByCityMapping[$price['type']] ?? null;

            if ($city !== null) {
                $result['clubPrices'][] = [
                    'type'            => $price['type'],
                    'value'           => $price['value'],
                    'discountedPrice' => $price['value'],
                    'city'            => $city,
                ];
            }
        }

        return $result;
    }

    /**
     * @return array
     *
     * @throws DomainException
     */
    public function mapUnits(): array
    {
        $this->checkModelSetting();

        $result = [];

        foreach ($this->model->getUnits() as $unit) {
            $result[] = [
                'name' => $unit['name'],
                'coefficient' => $unit['coefficient'],
                'base' => $unit['base'],
                'weight' => $unit['weight'],
                'volume' => $unit['volume'],
                'calc' => $unit['calc']
            ];
        }

        return $result;
    }

    /**
     * @return array
     *
     * @throws DomainException
     */
    public function buildFilters(): array
    {
        $this->checkModelSetting();

        $properties = $this->model->getProperties();

        if (empty($properties)) {
            return [];
        }

        $items = array_filter($properties, static function($item) { return $item['isFilter']; });

        $filters = [];

        foreach ($items as $item) {
            $property = $this->getPropertyData($item['id']);

            if ($property === null) {
                $this->logger->warning(sprintf('Неизвестное свойство: "%s" (%s)', $item['id'], $item['value']));

                continue;
            }

            if ($property['name'] === 'Бренд') {
                continue;
            }

            $filters[] = sprintf('%s:%s', $property['id'], trim($item['value']));
        }

        return $filters;
    }

    /**
     * @return Brand|null
     *
     * @throws MongoDBException
     */
    public function processBrand(): ?Brand
    {
        $this->checkModelSetting();

        $properties = $this->model->getProperties();

        if (empty($properties)) {
            return null;
        }

        $brandName = '';

        foreach ($properties as $item) {
            $property = $this->getPropertyData($item['id']);

            if ($property === null) {
                continue;
            }

            if ($property['name'] === 'Бренд') {
                $brandName = $item['value'];

                break;
            }
        }

        return $this->getBrand($brandName);
    }

    /**
     * @return array
     *
     * @throws MongoDBException
     */
    public function processSet(): array
    {
        $this->checkModelSetting();

        $set = $this->model->getSet();

        $ids = array_map(static function($item) {
            return $item['id'];
        }, $set);

        /** @var Product[] $products */
        $products = $this->productRepository->findByExternalIds($ids);

        $mappedSet = $this->setMapper($set);

        foreach($products as $product) {
            $mappedSet[$product->getExternalId()]['product'] = $product;
        }

        // @todo В комплектах есть ссылка на обрабатываемый товар, если этого товара ещё нет в базе, то данные комплекта будут не полные.
        // Это приводит к ошибке при создании товара server/src/Service/Creator/Catalog/ProductCreator.php:205
        // Нужно пересмотреть создание товара, и исключить подобный сценарий. Пока, как временное решение, такие товары загружаем без комплекта.
        $isCorrectData = true;

        foreach ($mappedSet as $set) {
            if (!isset($set['product'])) {
                $isCorrectData = false;
            }
        }

        return $isCorrectData ? $mappedSet : [];
    }

    /**
     * @return array
     */
    public function buildUnifyingProperties(): array
    {
        $this->checkModelSetting();

        $properties = $this->model->getProperties();

        if (empty($properties)) {
            return [];
        }

        $result = [];

        foreach ($properties as $property) {
            $item = $this->getPropertyData($property['id']);

            if ($item === null) {
                $this->logger->warning(sprintf('Неизвестное объединяющее свойство: "%s" (%s)', $property['id'], $property['value']));

                continue;
            }

            $result[] = sprintf('%s:%s', $item['id'], trim($property['value']));
        }

        return $result;
    }

    /**
     * @return array
     *
     * @throws MongoDBException
     *
     * @todo наверняка можно упростить...
     */
    public function mapBalance(): array
    {
        $this->checkModelSetting();

        $balance = $this->model->getBalance();

        $result = [];

        if (empty($balance)) {
            return $result;
        }

        $storeMap = [];
        $balanceMap = [];

        /** @var Store[] $stores */
        $stores = $this->storeRepository->createQueryBuilder()
            ->select('externalId', 'city', 'forCustomers')
            ->field('active')->equals(true)
            ->getQuery()
            ->execute();

        foreach ($stores as $store) {
            $storeMap[$store->getExternalId()] = [
                'cityId'       => $store->getCity()->getId(),
                'city'         => $store->getCity(),
                'forCustomers' => $store->isForCustomers(),
            ];
        }

        foreach ($balance as $item) {
            $stock = trim($item['stock']);

            if (isset($balanceMap[$stock])) {
                $balanceMap[$stock] += (float)$item['value'];
            } else {
                $balanceMap[$stock] = (float)$item['value'];
            }
        }

        foreach ($balanceMap as $storeId => $item) {
            if (isset($storeMap[$storeId])) {
                if (!$storeMap[$storeId]['forCustomers']) {
                    // В остатках для пользователей не учитываем внутренние склады
                    $this->logger->debug(
                        sprintf(
                            'Пропущена обработка остатков по складу "%s" - не разрешён для покупателей',
                            $storeId
                        )
                    );

                    continue;
                }

                if (isset($result[$storeMap[$storeId]['cityId']])) {
                    $result[$storeMap[$storeId]['cityId']]['count'] += $item;

                    continue;
                }
                $result[$storeMap[$storeId]['cityId']] = [
                    'city' => $storeMap[$storeId]['city'],
                    'count' => $item
                ];
            } else {
                $this->logger->info(
                    sprintf(
                        'Не удалось найти склад "%s" для остатка товара',
                        $storeId
                    )
                );
            }
        }

        return $result;
    }

    /**
     * @param string $externalId
     *
     * @return array|null
     */
    private function getPropertyData(string $externalId): ?array
    {
        if (empty($externalId)) {
            return null;
        }

        if (isset($this->storedFilters[$externalId])) {
            return $this->storedFilters[$externalId];
        }

        $property = $this->propertyRepository->findOneBy(['externalId' => $externalId]);

        if ($property === null) {
            return null;
        }

        $this->storedFilters[$externalId] = [
            'id'   => $property->getId(),
            'name' => $property->getName(),
        ];

        return $this->storedFilters[$externalId];
    }

    /**
     * @param string $brandName
     *
     * @return Brand|null
     *
     * @throws MongoDBException
     */
    private function getBrand(string $brandName): ?Brand
    {
        if (empty($brandName)) {
            return null;
        }

        $brandName = $this->processName($brandName);

        if (isset($this->storedBrands[str_replace('.', '', $brandName)])) {
            return $this->storedBrands[str_replace('.', '', $brandName)];
        }

        $brand = $this->brandRepository->findOneBy(['name' => $brandName]);

        if ($brand instanceof Brand) {
            return $brand;
        }

        $brand = new Brand();

        $brand
            ->setName($brandName)
            ->setActive(true);

        $brand = $this->brandCreator->create($brand);

        $this->storedBrands[str_replace('.', '', $brandName)] = $brand;

        $this->logger->info(
            sprintf(
                'Бренд: "%s" не был найден в базе и был добавлен деактивированным', $brandName
            )
        );

        return $brand;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function processName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    /**
     * @param array $set
     *
     * @return array
     */
    private function setMapper(array $set): array
    {
        $result = [];

        foreach ($set as $item) {
            $result[$item['id']]  = [
                'id' => $item['id'],
                'countCoefficient' => $item['value'],
                'isDefault' => is_bool($item['default']) ? $item['default'] : $item['default'] === 'true'
            ];
        }

        return $result;
    }

    /**
     * Для товаров, у которых базовая единица квадратные метры, включен калькулятор
     * и есть другие единицы измерения для пересчета, добавляем следующую проверку:
     *
     * если остатков в сумме по двум городам меньше, чем минимальная доступная для покупки единица (),
     * то обнуляем баланс у товара
     *
     * @param array $balance
     * @param array $units
     *
     * @return array
     */
    public function validateAndTruncateBalance(array $balance, array $units): array
    {
        if (
            $this->unitItemCalculator->findBaseUnit($units)['name'] === 'м2'
            &&
            $this->unitItemCalculator->isProductCalculatorEnable($units)
            &&
            count($units) > 1
        ) {
            /** @var array{name: string, coefficient: int, base: bool, weight: float, volume: float, calc: bool} $minUnitItem */
            $minUnitItem = $this->unitItemCalculator->getMinCalculableCoefficientUnit($units);

            try {
                $this->balanceCalculator->validateLimit($balance, $minUnitItem['coefficient']);
            } catch (LimitExceedingException $e) {

                /** @var array{city: string, count: int, limit: int} $balanceItem */

                return array_map(static function(array $balanceItem) {
                    $result = [
                        'city' => $balanceItem['city'],
                        'count' => 0
                    ];

                    if (isset($balanceItem['limit'])) {
                        $result['limit'] = $balanceItem['limit'];
                    }

                    return $result;
                }, $balance);
            }
        }

        return $balance;
    }

    /**
     * @param string $sectionId
     *
     * @throws MongoDBException|DomainException
     */
    public function storeFilterPositions(string $sectionId): void
    {
        $this->checkModelSetting();

        $properties = $this->model->getProperties();

        $items = array_filter($properties, static function($item) { return $item['isFilter']; });

        array_walk(
            $items,
            function (array $propertyItem, int $key) use ($sectionId) {
                if (!is_null($property = $this->getPropertyData($propertyItem['id']))) {
                    if (
                        is_null(
                            $filterPosition = $this->dm->getRepository(Product\Filter\FilterPosition::class)
                                ->findOneBy(
                                    [
                                        'sectionId'     => $sectionId,
                                        'propertyId'    => $property['id']
                                    ]
                                )
                        )
                    ) {
                        $this->dm->persist($this->buildFilterPosition($property['id'], $sectionId, $key));
                    } else {
                        $filterPosition->setIndex($key);
                    }
                }
            }
        );

        $this->dm->flush();
    }

    /**
     * @param array $balance
     *
     * @return int
     */
    public function getProductStatus(array $balance): int
    {
        $this->checkModelSetting();

        $isOnOrder = $this->model->getOnOrder()[0]['enabled'];
        $isNotEmptyBalance = (bool)array_reduce(
            $balance,
            static function ($carry, $item) {
                $carry += $item['count'];
                return $carry;
            }
        );

        return ProductService::determineProductStatus($isOnOrder, $isNotEmptyBalance);
    }

    /**
     * @param string $propertyId
     * @param string $sectionId
     * @param int    $index
     *
     * @return Product\Filter\FilterPosition
     */
    private function buildFilterPosition(string $propertyId, string $sectionId, int $index): Product\Filter\FilterPosition
    {
        return (new Product\Filter\FilterPosition())
            ->setPropertyId($propertyId)
            ->setSectionId($sectionId)
            ->setIndex($index);
    }

    /**
     * @return string
     */
    private function processNameByOffer(): string
    {
        $this->checkModelSetting();

        $name = $this->model->getName();
        $unifyingProperties = $this->model->getUnifyingProperties();

        return array_reduce(
            $unifyingProperties,
            static function ($carry, $property) {
                return sprintf('%s %s', $carry, $property['value']);
            },
            $name
        );
    }

    private function processStringField(string $value): ?string
    {
        return ($value) ? trim($value) : null;
    }

    /**
     * @param array $badges
     *
     * @return array
     */
    private function processBadges(array $badges): array
    {
        $result = [];

        foreach ($badges as $badge) {
            $badgeName = $this->processName($badge['value']);

            if (isset(Product::BADGES_PROCESS_MATCHING[$badgeName])) {
                $result[] =  Product::BADGES_PROCESS_MATCHING[$badgeName];
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    private function buildCharacteristic(): array
    {
        $this->checkModelSetting();

        $properties = $this->model->getProperties();

        if (empty($properties)) {
            return [];
        }

        $properties = array_filter(
            $properties,
            static function ($property) {
                return $property['isCharacteristic'];
            }
        );

        $characteristic = [];

        foreach ($properties as $property) {
            $item = $this->getPropertyData($property['id']);

            if ($item === null) {
                $this->logger->warning(
                    sprintf('Неизвестная характеристика: "%s" (%s)', $property['id'], $property['value'])
                );

                continue;
            }

            $characteristic[str_replace('.', '', $item['name'])] = trim($property['value']);
        }

        return $characteristic;
    }

    /**
     * Маппер для данных модели вида [{id: "a90b4d2c"}, {id: "4fb1d91"}...]
     *
     * @param array $data
     *
     * @return array
     */
    private function wrappedIdMapper(array $data): array
    {
        return array_map(
            static function ($item) {
                return $item['id'];
            },
            $data
        );
    }

    /**
     * Получает информацию о мультипликаторе баллов для товара
     *
     * @return float|int
     *
     * @todo После проектирования для карт PRO необходимо изменить метод,
     * чтобы он поддерживал передачу в БД множества мультипликаторов
     */
    private function getPointsMultiplier()
    {
        return 1;
    }

    /**
     * @return array
     *
     * @throws DomainException
     */
    private function getPointsMultipliers(): array
    {

        $this->checkModelSetting();

        $result = [];

        $statuses = $this->statusRepository->findAll();

        foreach($statuses as $status) {

            // по умолчанию ставим всем статусам из базы множитель 1
            $result[$status->getId()] = 1;

            foreach ($this->model->getPointsMultiplier() as $item) {
                if (
                    $item['status'] === $status->getExternalId()
                    ??  $item['multiplier'] > 1
                ) {
                    // если из 1С пришла корректная инфа по статусу, то ставим множитель из 1С
                    $result[$status->getId()] = $item['multiplier'];
                }
            }
        }

        return $result;
    }

    /**
     * @param array $priceByCityMap
     * @return array
     *
     * @throws MongoDBException
     */
    private function priceByCityMapProcessing(array $priceByCityMap): array
    {
        $result = [];

        $cities = $this->cityRepository->createQueryBuilder()
            ->find()
            ->field('parentId')->in(['', null])
            ->getQuery()
            ->execute();

        foreach ($cities as $city) {
            if (isset($priceByCityMap[$city->getName()])) {
                $result[$priceByCityMap[$city->getName()]] = $city;
            }
        }

        return $result;
    }
}
