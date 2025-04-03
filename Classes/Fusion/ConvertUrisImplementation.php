<?php
declare(strict_types=1);

namespace Flownative\WorkspacePreview\Fusion;

use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Exception as DomainException;
use Neos\Neos\Domain\Service\RenderingModeService;
use Neos\Neos\Fusion\ConvertUrisImplementation as OriginalImplementation;

class ConvertUrisImplementation extends OriginalImplementation
{

    /**
     * @Flow\Inject
     * @var RenderingModeService
     */
    protected $renderingModeService;

    /**
     * @return string
     * @throws DomainException
     */
    public function evaluate(): string
    {
        $currentRenderingMode = $this->renderingModeService->findByCurrentUser();
        $forceConversionPathPart = 'forceConversion';

        if ($currentRenderingMode->isEdit === false) {
            $fullPath = $this->path . '/' . $forceConversionPathPart;
            $this->fusionValueCache[$fullPath] = true;
        }

        return parent::evaluate();
    }

}
