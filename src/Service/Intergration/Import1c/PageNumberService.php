<?php

namespace App\Service\Integration\Import1C;

use Symfony\Component\Filesystem\Filesystem;

class PageNumberService
{
    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * PageNumberLogger constructor.
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    /**
     * @param int $count
     * @param int $pageSize
     * @return int
     */
    public function calcPagesNumber(int $count, int $pageSize): int
    {
        return ceil($count / $pageSize);
    }

    public function savePageNumber(string $pathPageNumberLog, string $pageNumber): void
    {
        if ($this->filesystem->exists($pathPageNumberLog)) {
            $handle = @fopen($pathPageNumberLog, 'c');

            // Заблокировать доступ на запись
            if (flock($handle, LOCK_EX)) {
                ftruncate($handle, 0); // Очистить файл
                fwrite($handle, $pageNumber); // Запись номера страницы в файл
                fflush($handle); // Сбросить буфер
                flock($handle, LOCK_UN); // Снять блокировку с файла
            }
        } else {
            $this->filesystem->appendToFile($pathPageNumberLog, $pageNumber);
        }
    }

    public function getPageNumber(string $pathPageNumberLog): string
    {
        $result = '';

        if ($this->filesystem->exists($pathPageNumberLog)) {
            // Открыть файл для чтения и поставить указатель на начало
            $handle = @fopen($pathPageNumberLog, 'r');

            // Заблокировать доступ на чтение
            if (flock($handle, LOCK_SH)) {
                $result = fread($handle, 255); // Чтение номера страницы
                flock($handle, LOCK_UN); // Снять блокировку с файла
            }
        }

        return $result;
    }

    public function removeLog(string $pathPageNumberLog): void
    {
        $this->filesystem->remove($pathPageNumberLog);
    }
}
