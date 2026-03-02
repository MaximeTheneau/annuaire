<?php
namespace App\Service;

use Imagine\Image\ImagineInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

class ImageOptimizer
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/avif',
        'image/webp',
    ];

    private const MAX_WIDTH  = 100;
    private const MAX_HEIGHT = 100;
    private const S3_FOLDER  = 'logos/';

    private $params;
    private $slugger;
    private $serializer;
    private $photoDir;
    private $projectDir;
    private $domainImg;
    private $s3Key;
    private $s3Secret;
    private $s3Region;
    private $s3Bucket;
    private $s3BucketFront;
    private $s3Version;
    private $s3Client;
    private ?ImagineInterface $imagine = null;
    private const IMAGE_SIZES = [320, 640, 750, 828, 1080, 1200, 1920, 2048, 3840];

    public function __construct(
        SluggerInterface $slugger,
        ContainerBagInterface $params,
        SerializerInterface $serializer,
        )
        {
            $this->slugger = $slugger;
            $this->params = $params;
            $this->serializer = $serializer;
            $this->photoDir =  $this->params->get('app.imgDir');
            $this->projectDir =  $this->params->get('app.projectDir');
            $this->s3Key = $this->params->get('amazon.s3.key');
            $this->s3Secret = $this->params->get('amazon.s3.secret');
            $this->s3Region = $this->params->get('amazon.s3.region');
            $this->s3Bucket = $this->params->get('amazon.s3.bucket');
            $this->s3BucketFront = $this->params->get('amazon.s3.bucket.front');
            $this->s3Version = $this->params->get('amazon.s3.version');
            $this->domainImg = $this->params->get('app.domain.img');
            $this->s3Client = new S3Client([
                'version' => $this->s3Version,
                'region' => $this->s3Region,
                'credentials' => [
                    'key' => $this->s3Key,
                    'secret' => $this->s3Secret,
                ],
            ]);
            // Driver initialisé à la première utilisation (lazy) pour ne pas bloquer le boot Symfony.
    }

private function getImagine(): \Imagine\Image\ImagineInterface
{
    if ($this->imagine === null) {
        if (extension_loaded('imagick')) {
            $this->imagine = new \Imagine\Imagick\Imagine();
        } elseif (extension_loaded('gd')) {
            // C'est ici que GD sera utilisé
            $this->imagine = new \Imagine\Gd\Imagine();
        } else {
            throw new \RuntimeException(
                'Aucun driver image disponible. Installez l\'extension PHP "imagick" ou "gd".'
            );
        }
    }

    return $this->imagine;
}

    /**
     * Vérifie que le fichier est bien une image autorisée (jpg, png, avif, webp).
     * Utilise finfo sur le contenu réel du fichier, pas l'extension.
     *
     * @throws \InvalidArgumentException si le type MIME n'est pas autorisé
     */
    private function validateImageType(File $file): void
    {
        $finfo    = new \finfo(\FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file->getRealPath());

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Type de fichier non autorisé : "%s". Seuls jpg, png, avif et webp sont acceptés.',
                $mimeType
            ));
        }
    }

    /**
     * Redimensionne l'image à 100×100 max (proportionnel) et la convertit en webp.
     */
    private function resizeAndSave(\Imagine\Image\ImageInterface $img, string $localPath): void
    {
        $size = $img->getSize();

        if ($size->getWidth() > self::MAX_WIDTH || $size->getHeight() > self::MAX_HEIGHT) {
            $img = $img->thumbnail(
                new Box(self::MAX_WIDTH, self::MAX_HEIGHT),
                ImageInterface::THUMBNAIL_INSET
            );
        }

        $img->strip()->save($localPath, ['webp_quality' => 65, 'webp_lossless' => false]);
    }

    public function setPicture(File $brochureFile, $company, $slug): void
    {
        $this->validateImageType($brochureFile);

        $localImagePath = $_ENV['IMG_DIR'] . $slug . '.webp';
        $s3Key          = self::S3_FOLDER . $slug . '.webp';

        $img = $this->getImagine()->open($brochureFile);
        $this->resizeAndSave($img, $localImagePath);

        // Suppression de l'ancienne image si elle existe
        if ($company->getImg() !== null) {
            $bucketDomain = $_ENV['DOMAIN_IMG'];
            $oldKey = str_replace($bucketDomain, '', $company->getImg());

            $this->s3Client->deleteObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $oldKey,
            ]);

            $this->s3Client->deleteMatchingObjects($this->s3BucketFront, self::S3_FOLDER . $slug . '.webp');

            $this->s3Client->deleteObject([
                'Bucket' => $this->s3BucketFront,
                'Key'    => $company->getImg(),
            ]);

            $slug  = $slug . '-' . rand(0, 10);
            $s3Key = self::S3_FOLDER . $slug . '.webp';

            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $s3Key,
                'Body'   => fopen($localImagePath, 'rb'),
            ]);
        }

        // Srcset
        $img    = $this->getImagine()->open($localImagePath);
        $imgUrl = $this->domainImg . $s3Key;
        $srcset = '';

        foreach (self::IMAGE_SIZES as $size) {
            if ($size <= $img->getSize()->getWidth()) {
                $srcset .= $imgUrl . '?width=' . $size . ' ' . $size . 'w,';
            }
        }
        $srcset .= $imgUrl . ' ' . $img->getSize()->getWidth() . 'w';

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $s3Key,
                'Body'   => fopen($localImagePath, 'rb'),
            ]);

            $company->setImg($imgUrl);
            $company->setSrcset($srcset);
            $company->setImgWidth($img->getSize()->getWidth());
            $company->setImgHeight($img->getSize()->getHeight());

        } catch (AwsException $e) {
            echo $e->getMessage();
        } finally {
            if (file_exists($localImagePath)) {
                unlink($localImagePath);
            }
        }
    }

    public function uploadToS3(File $file, string $slug): string
    {
        $this->validateImageType($file);

        $localImagePath = $_ENV['IMG_DIR'] . $slug . '.webp';
        $s3Key          = self::S3_FOLDER . $slug . '.webp';

        $img = $this->getImagine()->open($file);
        $this->resizeAndSave($img, $localImagePath);

        try {
            $this->s3Client->putObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $s3Key,
                'Body'   => fopen($localImagePath, 'rb'),
            ]);
        } catch (AwsException $e) {
            throw $e;
        } finally {
            if (file_exists($localImagePath)) {
                unlink($localImagePath);
            }
        }

        return $this->domainImg . $s3Key;
    }

    /**
     * Supprime une image depuis son URL complète (ex: https://cdn.example.com/logos/slug.webp).
     * Utilisé pour la suppression depuis le CRUD admin.
     */
    public function clearImage(string $imgUrl): void
    {
        $key = str_replace($this->domainImg, '', $imgUrl);

        try {
            $this->s3Client->deleteObject(['Bucket' => $this->s3Bucket, 'Key' => $key]);
            $this->s3Client->deleteMatchingObjects($this->s3BucketFront, $key);
            $this->s3Client->deleteObject(['Bucket' => $this->s3BucketFront, 'Key' => $key]);
        } catch (AwsException $e) {
            echo $e->getMessage();
        }
    }

    public function deletedPicture(string $slug): void
    {
        $s3Key = self::S3_FOLDER . $slug . '.webp';

        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->s3Bucket,
                'Key'    => $s3Key,
            ]);

            $this->s3Client->deleteMatchingObjects($this->s3BucketFront, $s3Key);

            $this->s3Client->deleteObject([
                'Bucket' => $this->s3BucketFront,
                'Key'    => $s3Key,
            ]);
        } catch (AwsException $e) {
            echo $e->getMessage();
        }
    }
}
