<?php

namespace App\Service\Integration\Import1C\Catalog\Product\Model;

use App\Exception\Itegration\Import1C\Product\ProductImportModelBuildingException;
use App\Repository\SectionRepository;
use App\Service\Validator\DocumentValidator;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductModelBuilder
{
    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * @var SectionRepository
     */
    private SectionRepository $sectionRepository;

    /**
     * @var DocumentValidator
     */
    private DocumentValidator $validator;

    /**
     * @param SerializerInterface $serializer
     * @param SectionRepository $sectionRepository
     * @param DocumentValidator $validator
     */
    public function __construct(
        SerializerInterface $serializer,
        SectionRepository $sectionRepository,
        DocumentValidator $validator
    )
    {
        $this->serializer = $serializer;
        $this->sectionRepository = $sectionRepository;
        $this->validator = $validator;
    }

    /**
     * @throws ExceptionInterface|NotNormalizableValueException|HttpException
     */
    public function buildFromArray(array $data): ProductImportModel
    {
        $model =  $this->serializer->denormalize($data, ProductImportModel::class);

        $this->validator->validate($model);

        return $model;
    }

    /**
     * @param string $data
     *
     * @return ProductImportModel
     *
     * @throws NotNormalizableValueException|HttpException
     */
    public function buildFromString(string $data): ProductImportModel
    {
        $model = $this->serializer->deserialize($data, ProductImportModel::class, 'json');

        $this->validator->validate($model);

        return $model;
    }

    /**
     * @throws ProductImportModelBuildingException
     */
    public function validate(ProductImportModel $model): void
    {
        if (is_null($this->sectionRepository->findOneByExternalId($model->getSection()))) {
            throw new ProductImportModelBuildingException(sprintf('не удалось найти раздел. SectionExternalId: %s', $model->getSection()));
        }
    }
}
