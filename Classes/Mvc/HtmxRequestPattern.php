<?php

/*
 * This file is part of the Neos.Workspace.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Flownative\WorkspacePreview\Mvc;

use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Security\RequestPatternInterface;

/**
 * @internal for communication within the Workspace UI only
 */
final class HtmxRequestPattern implements RequestPatternInterface
{
    public function matchRequest(ActionRequest $request): bool
    {
        return $request->getFormat() === 'htmx' && $request->getControllerPackageKey() === 'Flownative.WorkspacePreview';
    }
}
