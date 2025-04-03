<?php
declare(strict_types=1);

namespace Flownative\WorkspacePreview\Controller;

use Flownative\WorkspacePreview\WorkspacePreviewTokenFactory;
use Neos\ContentRepository\Core\SharedModel\Workspace\Workspace;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Error\Messages\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;

/**
 * Service controller to refresh a preview token for a given workspace.
 */
class HashTokenRefreshController extends ActionController
{
    /**
     * @Flow\Inject
     * @var WorkspacePreviewTokenFactory
     */
    protected $workspacePreviewTokenFactory;

    /**
     * Will refresh the hash or create an entirely new token for the given workspace
     *
     * @param Workspace $workspace
     */
    public function refreshHashTokenForWorkspaceAction(string $workspaceName): void
    {
        $this->workspacePreviewTokenFactory->refresh(WorkspaceName::fromString($workspaceName));
        $this->addFlashMessage('A new preview token has been generated for workspace "%s", the old one is invalid now!', '', Message::SEVERITY_OK, [$workspaceName]);
        $this->redirect("index", "Workspace", "Neos.Workspace.Ui");
    }
}
