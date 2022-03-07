<?php

namespace App\Service\Integration\Import1C\Catalog\Product;

use App\Service\Integration\Import1C\BaseGateway;
use App\Service\Integration\Import1C\GatewayException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

class ProductImportGateway extends BaseGateway
{
    /** @var string */
    protected const API_TYPE = 'ProductList';

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /** @var int */
    private int $timeoutBetweenRequests;

    /** @var int */
    private int $numberAttempts;

    /**
     * ProductImportGateway constructor.
     *
     * @param HttpClientInterface $api1c
     * @param ParameterBagInterface $parameterBag
     * @param LoggerInterface $importProductSenderLogger
     */
    public function __construct(
        HttpClientInterface $api1c,
        ParameterBagInterface $parameterBag,
        LoggerInterface $importProductSenderLogger
    ) {
        parent::__construct($api1c, $parameterBag);

        $this->logger = $importProductSenderLogger;
        $this->timeoutBetweenRequests = $parameterBag->get('import1c.timeout.between_requests');
        $this->numberAttempts = $parameterBag->get('import1c.number_attempts');
    }

    /**
     * @param int $pageNumber
     * @param string|null $externalId
     *
     * @return array
     *
     * @throws GatewayException
     * @throws JsonException
     */
    public function requestProduct(int $pageNumber = 0, ?string $externalId = null): array
    {
        for ($i = 1; $i < $this->numberAttempts; $i++) {
            try {
                $this->logger->debug(sprintf('Начало запроса страницы %d. Попытка %d', $pageNumber, $i));

                $start = microtime(true);

                $response = $this->request(
                    self::METHOD_POST,
                    [
                        'page'    => (string)$pageNumber,
                        'nomenkl' => $externalId ?? '',
                    ]
                );

                $result = $response->toArray();

                $finish = microtime(true);

                $this->logger->debug(sprintf('Запрос завершился за %f сек', $finish - $start));

                return $result;
            } catch (ExceptionInterface $e) {
                $this->logger->warning(sprintf('Произошла ошибка "%s" (%s)', $e->getMessage(), $e->getCode()));
            }

            usleep($this->timeoutBetweenRequests * $i);
        }

        throw new GatewayException(
            sprintf(
                'Не удалось получить список торговых предложений из 1С после %s попыток',
                $this->numberAttempts
            ),
            504
        );
    }
}
