<?php

namespace App\Service\Integration\Import1C\Catalog\Product;

use App\Exception\Itegration\Import1C\Product\ProductImportModelBuildingException;
use App\Repository\SectionRepository;
use App\Service\Integration\Import1C\Catalog\Product\Model\ProductImportModel;
use App\Service\Integration\Import1C\Catalog\Product\Message\Sender\ProductMessageSender;
use App\Service\Integration\Import1C\Catalog\Product\Model\ProductModelBuilder;
use App\Service\Integration\Import1C\GatewayException;
use App\Service\Integration\Import1C\PageNumberService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Exception as SerializerException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerInterface;

class ProductImportService
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var ProductImportGateway
     */
    private ProductImportGateway $gateway;

    /**
     * @var string
     */
    protected string $pageSizeDefault;

    /**
     * @var ProductMessageSender
     */
    protected ProductMessageSender $sender;

    /**
     * @var string
     */
    protected string $pageNumberLog;

    /**
     * @var PageNumberService
     */
    protected PageNumberService $pageNumberService;

    /**
     * @var SerializerInterface
     */
    protected SerializerInterface $serializer;

    /**
     * @var SectionRepository
     */
    protected SectionRepository $sectionRepository;

    /**
     * @var ProductModelBuilder
     */
    protected ProductModelBuilder $modelBuilder;

    /**
     * ProductImportService constructor.
     *
     * @param LoggerInterface       $importProductSenderLogger
     * @param ProductImportGateway  $gateway
     * @param ParameterBagInterface $parameterBag
     * @param ProductMessageSender  $sender
     * @param PageNumberService     $pageNumberService
     * @param SerializerInterface $serializer
     * @param SectionRepository $sectionRepository
     * @param ProductModelBuilder $modelBuilder
     */
    public function __construct(
        LoggerInterface $importProductSenderLogger,
        ProductImportGateway $gateway,
        ParameterBagInterface $parameterBag,
        ProductMessageSender $sender,
        PageNumberService $pageNumberService,
        SerializerInterface $serializer,
        SectionRepository $sectionRepository,
        ProductModelBuilder $modelBuilder
    ) {
        $this->logger                   = $importProductSenderLogger;
        $this->gateway                  = $gateway;
        $this->sender                   = $sender;
        $this->pageNumberService        = $pageNumberService;
        $this->pageNumberLog            = $parameterBag->get('import1c.product.page.log');
        $this->pageSizeDefault          = $parameterBag->get('import1c.product.page_size');
        $this->serializer               = $serializer;
        $this->sectionRepository        = $sectionRepository;
        $this->modelBuilder             = $modelBuilder;
    }

    /**
     * @return int - общее количество обработанных пакетов
     */
    public function import(): int
    {
        try {
            $this->logger->info('Запущен процесс загрузки товаров');
            $this->logger->info(sprintf('Memory get usage: %s', memory_get_usage()));

            $this->logger->info('Проверяем, если выгрузка прерывалась, есть ли сохраненный номер страницы.');
            $currentPageNumber = $this->pageNumberService->getPageNumber($this->pageNumberLog);

            if (empty($currentPageNumber)) {
                $currentPageNumber = 0;
                $this->logger->info('Нет сохраненного номера страницы');
            }

            $this->logger->info(sprintf('Выгрузка начнётся с "%s-й" страницы', $currentPageNumber));

            $request = $this->getProducts($currentPageNumber);

            $this->logger->info(
                sprintf(
                    'Получаем пакет товаров page_size: %s page_number: %s',
                    $this->pageSizeDefault,
                    $currentPageNumber
                )
            );

            $products = $request['result']['items'];

            $this->logger->info(
                sprintf(
                    'Обрабатываем пакет товаров page_size: %s page_number: %s',
                    $this->pageSizeDefault,
                    $currentPageNumber
                )
            );

            $this->processProduct($products);

            $pageCount = $this->pageNumberService->calcPagesNumber($request['result']['count'], $this->pageSizeDefault);

            $this->logger->info(
                sprintf('Количество пакетов %s', $pageCount)
            );

            ++$currentPageNumber;

            $this->pageNumberService->savePageNumber($this->pageNumberLog, $currentPageNumber);

            for ($i = $currentPageNumber; $i < $pageCount; $i++) {
                $this->logger->info(
                    sprintf('Получаем пакет товаров page_size: %s page_number: %s', $this->pageSizeDefault, $i)
                );
                $request = $this->getProducts($i);

                $this->logger->info(
                    sprintf('Обрабатываем пакет товаров page_size: %s page_number: %s', $this->pageSizeDefault, $i)
                );

                $this->processProduct($request['result']['items']);

                $this->pageNumberService->savePageNumber($this->pageNumberLog, $i);

                $this->logger->info(sprintf('Memory get usage: %s', memory_get_usage()));
            }

            $this->pageNumberService->removeLog($this->pageNumberLog);

            $this->logger->info(sprintf('Memory get PEAK usage: %s', memory_get_peak_usage()));
            $this->logger->info('Закончен процесс загрузки товаров');

            return $pageCount;
        } catch (Exception $exception) {
            $this->logger->critical(
                'Не удалось обработать данные или добавить в очередь',
                [
                    'ERROR' => $exception->getMessage()
                ]
            );
        }
    }

    /**
     * @param int $pageNumber
     *
     * @return array
     * @throws GatewayException
     */
    protected function getProducts(int $pageNumber = 0): array
    {
        try {
            return $this->gateway->requestProduct($pageNumber);
        } catch (GatewayException $exception) {
            $this->logger->critical(sprintf('Code: %s Error: %s', $exception->getCode(), $exception->getMessage()));

            throw $exception;
        }
    }

    /**
     * @param array $products
     */
    protected function processProduct(array $products): void
    {
        foreach ($products as $product) {
            try {
                $model = $this->modelBuilder->buildFromArray($product);

                $this->modelBuilder->validate($model);

                $this->appendToQueue($model);
            } catch (
                SerializerException\ExceptionInterface |
                NotNormalizableValueException |
                HttpException
            $e) {
                $this->logger->error(
                    'Ошибка структуры исходных данных при создании модели сообщения - пропускаем товар', [
                    'error' => $e->getMessage(),
                    'data'  => $product
                ]);
            } catch (ProductImportModelBuildingException $e) {
                $this->logger->error('Ошибка импорта товара - пропускаем товар', [
                    'error' => $e->getMessage(),
                    'data'  => $product
                ]);
            }
        }
    }

    /**
     * @param ProductImportModel $model
     *
     */
    private function appendToQueue(ProductImportModel $model): void
    {
        $this->sender->send(
            (new Message\Message())
                ->setMessage(
                    $this->serializer->serialize($model, 'json')
                )
        );
    }
}
