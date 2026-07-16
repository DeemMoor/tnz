<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\RegistrationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Приём и обработка аватарок: валидация, обрезка в квадрат, ресайз, сохранение
 * в public/uploads/avatars/. Старый файл игрока удаляется при замене.
 */
final class AvatarUploader
{
    private const int SIZE = 256;                 // сторона квадрата, px
    private const int MAX_BYTES = 5 * 1024 * 1024; // 5 МБ

    public function __construct(
        private readonly string $avatarsDir,
    ) {
    }

    /**
     * Сохранить загруженный файл как аватар пользователя. Возвращает имя файла.
     *
     * @throws RegistrationException при неверном типе/размере
     */
    public function upload(User $user, UploadedFile $file): string
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new RegistrationException('Файл слишком большой (до 5 МБ)', 422);
        }

        $image = $this->createImage($file);

        // Обрезаем по центру в квадрат и масштабируем до SIZE.
        $w = imagesx($image);
        $h = imagesy($image);
        $side = min($w, $h);
        $srcX = (int) (($w - $side) / 2);
        $srcY = (int) (($h - $side) / 2);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagecopyresampled($square, $image, 0, 0, $srcX, $srcY, self::SIZE, self::SIZE, $side, $side);
        imagedestroy($image);

        if (!is_dir($this->avatarsDir)) {
            mkdir($this->avatarsDir, 0775, true);
        }

        $filename = 'u' . $user->getId() . '_' . bin2hex(random_bytes(6)) . '.jpg';
        imagejpeg($square, $this->avatarsDir . '/' . $filename, 85);
        imagedestroy($square);

        // Удаляем старую аватарку, если была.
        $this->deleteCurrent($user);

        return $filename;
    }

    public function deleteCurrent(User $user): void
    {
        $old = $user->getAvatarPath();
        if ($old !== null && $old !== '' && is_file($this->avatarsDir . '/' . $old)) {
            @unlink($this->avatarsDir . '/' . $old);
        }
    }

    private function createImage(UploadedFile $file): \GdImage
    {
        $data = @file_get_contents($file->getPathname());
        $image = $data !== false ? @imagecreatefromstring($data) : false;
        if ($image === false) {
            throw new RegistrationException('Не удалось прочитать изображение (нужен JPG/PNG)', 422);
        }

        return $image;
    }
}
