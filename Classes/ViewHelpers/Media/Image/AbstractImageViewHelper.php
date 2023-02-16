<?php
namespace FluidTYPO3\Vhs\ViewHelpers\Media\Image;

/*
 * This file is part of the FluidTYPO3/Vhs project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Vhs\Utility\ContextUtility;
use FluidTYPO3\Vhs\Utility\FrontendSimulationUtility;
use FluidTYPO3\Vhs\ViewHelpers\Media\AbstractMediaViewHelper;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Base class for image related view helpers adapted from FLUID
 * original image viewhelper.
 */

abstract class AbstractImageViewHelper extends AbstractMediaViewHelper
{
    /**
     * @var ContentObjectRenderer
     */
    protected $contentObject;

    /**
     * @var ConfigurationManagerInterface
     */
    protected $configurationManager;

    /**
     * Result of \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer::getImgResource()
     * @var array|null
     */
    protected $imageInfo;

    public function injectConfigurationManager(ConfigurationManagerInterface $configurationManager): void
    {
        $this->configurationManager = $configurationManager;
        /** @var ContentObjectRenderer $contentObject */
        $contentObject = $this->configurationManager->getContentObject();
        $this->contentObject = $contentObject;
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument(
            'width',
            'string',
            'Width of the image. This can be a numeric value representing the fixed width of the image in pixels. ' .
            'But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width ' .
            'for possible options.'
        );
        $this->registerArgument(
            'height',
            'string',
            'Height of the image. This can be a numeric value representing the fixed height of the image in pixels. ' .
            'But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width ' .
            'for possible options.'
        );
        $this->registerArgument('maxW', 'integer', 'Maximum Width of the image. (no upscaling)');
        $this->registerArgument('maxH', 'integer', 'Maximum Height of the image. (no upscaling)');
        $this->registerArgument('minW', 'integer', 'Minimum Width of the image.');
        $this->registerArgument('minH', 'integer', 'Minimum Height of the image.');
        $this->registerArgument(
            'format',
            'string',
            'Format of the processed file - also determines the target file format. If blank, TYPO3/IM/GM default is ' .
            'taken into account.'
        );
        $this->registerArgument(
            'quality',
            'integer',
            'Quality of the processed image. If blank/not present falls back to the default quality defined in ' .
            'install tool.',
            false,
            $GLOBALS['TYPO3_CONF_VARS']['GFX']['jpg_quality'] ?? 90
        );
        $this->registerArgument(
            'treatIdAsReference',
            'boolean',
            'When TRUE treat given src argument as sys_file_reference record. Applies only to TYPO3 6.x and above.',
            false,
            false
        );
        $this->registerArgument('canvasWidth', 'integer', 'Width of an optional canvas to place the image on.');
        $this->registerArgument('canvasHeight', 'integer', 'Height of an optional canvas to place the image on.');
        $this->registerArgument(
            'canvasColor',
            'string',
            'Background color of an optional canvas to place the image on (hex triplet).'
        );
        $this->registerArgument(
            'transparencyColor',
            'string',
            'Color to set transparent when using canvas feature (hex triplet).'
        );
        $this->registerArgument('crop', 'string', 'Information generated by the backend\'s graphical cropping UI');
        $this->registerArgument(
            'graceful',
            'bool',
            'Set to TRUE to ignore files that cannot be loaded. Default behavior is to throw an Exception.',
            false,
            false
        );
    }

    public function preprocessImage(?string $imageSource = null): void
    {
        $src = (null === $imageSource) ? $this->arguments['src'] : $imageSource;
        $width = $this->arguments['width'];
        $height = $this->arguments['height'];
        $minW = $this->arguments['minW'];
        $minH = $this->arguments['minH'];
        $maxW = $this->arguments['maxW'];
        $maxH = $this->arguments['maxH'];
        $format = $this->arguments['format'];
        $quality = $this->arguments['quality'];
        $treatIdAsReference = (boolean) $this->arguments['treatIdAsReference'];
        $crop = $this->arguments['crop'];

        if ($src instanceof FileReference) {
            if ($crop === null) {
                $crop = $src->_getProperty('crop');
            }
            $src = $src->getUid();
            $treatIdAsReference = true;
        }

        $tsfeBackup = FrontendSimulationUtility::simulateFrontendEnvironment();

        $setup = [
            'width' => $width,
            'height' => $height,
            'minW' => $minW,
            'minH' => $minH,
            'maxW' => $maxW,
            'maxH' => $maxH,
            'treatIdAsReference' => $treatIdAsReference,
            'crop' => $crop,
        ];
        if (!empty($format)) {
            $setup['ext'] = $format;
        }
        if (0 < (integer) $quality) {
            $quality = MathUtility::forceIntegerInRange($quality, 10, 100, 75);
            $setup['params'] = '-quality ' . $quality;
        }

        if (ContextUtility::isBackend() && strpos($src, '../') === 0) {
            $src = mb_substr($src, 3);
        }
        $this->imageInfo = $this->contentObject->getImgResource($src, $setup);

        if (!is_array($this->imageInfo)) {
            if ($this->arguments['graceful'] ?? false) {
                $this->mediaSource = '';
                FrontendSimulationUtility::resetFrontendEnvironment($tsfeBackup);
                return;
            }
            throw new Exception('Could not get image resource for "' . htmlspecialchars($src) . '".', 1253191060);
        }

        $GLOBALS['TSFE']->lastImageInfo = $this->imageInfo;

        if ($this->hasArgument('canvasWidth') && $this->hasArgument('canvasHeight')) {
            $canvasWidth = (integer) $this->arguments['canvasWidth'];
            $canvasHeight = (integer) $this->arguments['canvasHeight'];
            $canvasColor = str_replace('#', '', $this->arguments['canvasColor']);
            $originalFilename = $this->imageInfo[3];
            $originalExtension = mb_substr($originalFilename, -3);
            $tempPath = 'typo3temp/assets/';
            $destinationFilename = $tempPath . 'vhs-canvas-' .
                md5($originalFilename.$canvasColor.$canvasWidth.$canvasHeight) . '.' . $originalExtension;
            $destinationFilepath = GeneralUtility::getFileAbsFileName($destinationFilename);
            $transparency = '';
            if ($this->hasArgument('transparencyColor')) {
                $transparencyColor = str_replace('#', '', $this->arguments['transparencyColor']);
                $transparency = ' -transparent \'#' . $transparencyColor . '\'';
            }
            if (!file_exists($destinationFilepath)) {
                $arguments = sprintf(
                    '%s -background \'#%s\'%s -gravity center -extent %dx%d %s',
                    $originalFilename,
                    $canvasColor,
                    $transparency,
                    $canvasWidth,
                    $canvasHeight,
                    $destinationFilepath
                );
                $command = CommandUtility::imageMagickCommand('convert', $arguments);
                CommandUtility::exec($command);
            }
            $this->imageInfo[3] = $destinationFilename;
        }

        $GLOBALS['TSFE']->imagesOnPage[] = $this->imageInfo[3];
        $this->mediaSource = rawurldecode($this->imageInfo[3]);

        FrontendSimulationUtility::resetFrontendEnvironment($tsfeBackup);
    }
}
