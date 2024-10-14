<?php
namespace FluidTYPO3\Vhs\ViewHelpers\Page;

/*
 * This file is part of the FluidTYPO3/Vhs project under GPLv2 or later.
 *
 * For the full copyright and license information, please read the
 * LICENSE.md file that was distributed with this source code.
 */

use FluidTYPO3\Vhs\Utility\TsfeUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * ### Page: Static Prefix
 *
 * Use this ViewHelper to read the contents of the `plugin.tx_vhs.settings.prependPath`
 * TypoScript location - this setting stores the static prefix which gets added to all
 * relative resource URIs generated by VHS; whenever you require a ViewHelper which
 * does not respect this setting you can use this ViewHelper to prepend that setting
 * after the value is returned from the other ViewHelper.
 */
class StaticPrefixViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @var boolean
     */
    protected $escapeOutput = false;

    /**
     * @return mixed
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        return (new TsfeUtility())->getTyposcriptSetupArray()['plugin.']['tx_vhs.']['settings.']['prependPath'] ?? '';
    }
}
