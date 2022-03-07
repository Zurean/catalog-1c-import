<?php

namespace App\Command;

use App\Service\Integration\Import1C\Catalog\Offer\OfferImportService;
use App\Service\Integration\Import1C\Catalog\Package\Remove\PackageRemoveService;
use App\Service\Integration\Import1C\Catalog\Package\Update\PackageUpdateService;
use App\Service\Integration\Import1C\Catalog\Product\ProductImportService;
use Doctrine\ODM\MongoDB\MongoDBException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProductImportCommand extends Command
{
    protected static $defaultName = '1c:product-import';
    
    /**
     * @var PackageUpdateService
     */
    private PackageUpdateService $packageUpdate;
    
    /**
     * @var ProductImportService
     */
    private ProductImportService $productImport;
    
    /**
     * @var PackageRemoveService
     */
    private PackageRemoveService $packageRemove;
    
    /**
     * @var OfferImportService
     */
    private OfferImportService $offerImport;
    
    /**
     * ProductImportCommand constructor.
     *
     * @param ProductImportService $productImport
     * @param OfferImportService   $offerImport
     * @param PackageUpdateService $packageUpdate
     * @param PackageRemoveService $packageRemove
     */
    public function __construct(
        ProductImportService $productImport,
        OfferImportService $offerImport,
        PackageUpdateService $packageUpdate,
        PackageRemoveService $packageRemove
    ) {
        parent::__construct();
        
        $this->packageUpdate    = $packageUpdate;
        $this->productImport    = $productImport;
        $this->packageRemove    = $packageRemove;
        $this->offerImport      = $offerImport;
    }


    protected function configure(): void
    {
        $this
            ->setDescription(
                'Выгружает товары и торговые предложения каталога из 1С и отправляет в очередь сообщений'
            )
            ->addArgument(
                'packageUpdate',
                InputArgument::OPTIONAL,
                'false - если обновление пакетов не требуется, или true - если нужно запросить обновление пакетов.',
                'false'
            )
            ->addArgument(
                'isNeedChanges',
                InputArgument::OPTIONAL,
                'false - если нужна полная выгрузка, или true - если только изменения.',
                'false'
            )
            ->addArgument(
                'packageClear',
                InputArgument::OPTIONAL,
                'false - если очищать пакеты не требуется, или true - если после окончания нужно очистить обработанные пакеты.',
                'false'
            );
    }
    
    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $packageUpdate = $input->getArgument('packageUpdate') === 'true';
        $packageClear = $input->getArgument('packageClear') === 'true';

        if ($packageUpdate) {
            $isNeedChanges = $input->getArgument('isNeedChanges') === 'true';
            $this->packageUpdate->update($isNeedChanges);
        }

        $productsPageCount = $this->productImport->import();

        if ($packageClear) {
            $this->packageRemove->remove($productsPageCount, true);
        }

        return Command::SUCCESS;
    }
}
