<?php

namespace App\Service\Integration\Import1C\Catalog\Product\Message\Handler;

use App\Exception\Itegration\Import1C\Product\ProductImportModelBuildingException;
use App\Service\Cache\CacheManager;
use App\Service\Integration\Import1C\Catalog\Product\Message\Message;
use App\Service\Integration\Import1C\Catalog\Product\Model\ModelMapper;
use App\Service\Integration\Import1C\Catalog\Product\Model\ProductModelBuilder;
use Psr\Cache\InvalidArgumentException;
use App\Document\Product;
use App\Document\Section;
use App\Repository\ProductRepository;
use App\Repository\SectionRepository;
use App\Service\Creator\Catalog\ProductCreator;
use App\Service\Updater\Catalog\ProductUpdater;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

class ProductMessageHandler implements MessageHandlerInterface
{
    /**
     * @var ProductCreator
     */
    private ProductCreator $creator;
    /**
     * @var ProductUpdater
     */
    private ProductUpdater $updater;
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;
    /**
     * @var SectionRepository
     */
    private SectionRepository $sectionRepository;
    /**
     * @var ProductRepository
     */
    private ProductRepository $productRepository;

    private CacheManager $cacheManager;

    private int $itemCounter = 0;

    /**
     * @var ProductModelBuilder
     */
    private ProductModelBuilder $modelBuilder;

    /**
     * @var ModelMapper
     */
    private ModelMapper $modelMapper;

    /**
     * ProductMessageHandler constructor.
     *
     * @param ProductCreator $creator
     * @param ProductUpdater $updater
     * @param LoggerInterface $importProductHandlerLogger
     * @param SectionRepository $sectionRepository
     * @param ProductRepository $productRepository
     * @param CacheManager $cacheManager
     * @param ProductModelBuilder $modelBuilder
     * @param ModelMapper $modelMapper
     */
    public function __construct(
        ProductCreator $creator,
        ProductUpdater $updater,
        LoggerInterface $importProductHandlerLogger,
        SectionRepository $sectionRepository,
        ProductRepository $productRepository,
        CacheManager $cacheManager,
        ProductModelBuilder $modelBuilder,
        ModelMapper $modelMapper
    ) {
        $this->creator                  = $creator;
        $this->updater                  = $updater;
        $this->logger                   = $importProductHandlerLogger;
        $this->sectionRepository        = $sectionRepository;
        $this->productRepository        = $productRepository;
        $this->cacheManager             = $cacheManager;

        $this->modelBuilder = $modelBuilder;

        $this->modelMapper = $modelMapper;
    }

    /**
     * @param Message $message
     *
     * @todo кроме этого, нужен обработчик очереди ошибочных сообщений
     */
    public function __invoke(Message $message)
    {
        $this->itemCounter++;

        try {
            if (empty($message->getMessage())) {
                throw new ProductMessageHandlerException(
                    sprintf('Не удалось получить данные из сообщения: %s', $message->getMessage())
                );
            }

            $model = $this->modelBuilder->buildFromString($message->getMessage());
            $this->modelBuilder->validate($model);
            $this->modelMapper->loadModel($model);

            /** @var Product $product */
            $product = $this->productRepository->findOneByExternalId($model->getId());

            if (is_null($product)) {
                $product = $this->creator->createNew(
                    $model->getId(),
                    $model->getName(),
                    (string)$model->getCode(),
                    true
                );
            } else {
                /** @var Section $section */
                $section = $this->sectionRepository->findOneByExternalId($model->getSection());

                $discounts          = $this->modelMapper->mapDiscounts();
                $prices             = $this->modelMapper->mapPrices();
                $units              = $this->modelMapper->mapUnits();
                $filters            = $this->modelMapper->buildFilters();
                $brand              = $this->modelMapper->processBrand();
                $balance            = $this->modelMapper->validateAndTruncateBalance(
                    $this->modelMapper->mapBalance(),
                    $units
                );
                $set                = $this->modelMapper->processSet();
                $unifyingProperties = $this->modelMapper->buildUnifyingProperties();

                $this->modelMapper->storeFilterPositions($section->getId());

                // @todo найти возможность заменить на валидацию в документе - Assert\NotBlank не проходит
                if (empty($prices['prices'])) {
                    throw new ProductMessageHandlerException(
                        sprintf(
                            'Не найдено допустимых типов цен для данного товара. ExternalId: %s',
                            $model->getId()
                        )
                    );
                }

                $prices = array_merge($prices['prices'], $prices['clubPrices']);

                $model->setStatus($this->modelMapper->getProductStatus($balance));

                $this->updater->update(
                    $product,
                    $this->modelMapper->mapData(),
                    $prices,
                    $discounts,
                    $units,
                    $filters,
                    $section,
                    $balance,
                    $brand,
                    $unifyingProperties,
                    $set,
                    true
                );
            }

            $this->cacheManager->invalidateTags([Product::getCacheTag($product->getId())]);
        } catch (NotNormalizableValueException|HttpException|ProductImportModelBuildingException $exception) {
            $this->logger->error(
                sprintf(
                    'Не удалось построить модель импорта из данных сообщения'
                ),
                [
                    'MESSAGE' => $message->getMessage(),
                    'ERROR' => $exception->getMessage()
                ]
            );
        } catch (\Exception $exception) {
            $this->logger->error(
                sprintf(
                    'Не удалось обработать товар externalId: %s', $model->getId()
                ),
                [
                    'ERROR' => $exception->getMessage(),
                    'target' => sprintf('%s:%s', $exception->getFile(), $exception->getLine()),
                    'stack' => $exception->getTrace()
                ]
            );
        } catch (InvalidArgumentException $e) {
            $this->logger->warning(
                sprintf(
                    'Не удалось сбросить кеш для товара externalId: %s', $model->getId()
                ),
                [
                    'MESSAGE' => $e->getMessage()
                ]
            );
        }

        if ($this->itemCounter > 1000) {
            // Плановый выход после обработки 100 записей
            $this->logger->info('Обработано 1000 записей. Обработчик планово завершает работу');

            exit;
        }
    }
}
