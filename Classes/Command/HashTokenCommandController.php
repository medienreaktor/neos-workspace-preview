<?php
declare(strict_types=1);

namespace Flownative\WorkspacePreview\Command;

use Flownative\WorkspacePreview\WorkspacePreviewTokenFactory;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Neos\Domain\Service\WorkspaceService;

/**
 *
 */
class HashTokenCommandController extends CommandController {
    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @Flow\Inject
     * @var WorkspacePreviewTokenFactory
     */
    protected $workspacePreviewTokenFactory;

    /**
     * @Flow\Inject
     * @var ContentRepositoryRegistry
     */
    protected $contentRepositoryRegistry;

    /**
     * @Flow\Inject
     * @var WorkspaceService
     */
    protected $workspaceService;

    /**
     * Create a token for previewing the workspace with the given name/identifier.
     *
     * @param string $workspaceName
     */
    public function createWorkspacePreviewTokenCommand(string $workspaceName): void {
        $this->createAndOutputWorkspacePreviewToken($workspaceName);
        $this->persistenceManager->persistAll();
    }

    /**
     * Create preview tokens for all internal and private workspaces (not personal though)
     */
    public function createForAllPossibleWorkspacesCommand(string $contentRepository = 'default'): void {
        $contentRepositoryId = ContentRepositoryId::fromString($contentRepository);
        $workspaces = $this->contentRepositoryRegistry->get($contentRepositoryId)->findWorkspaces();
        foreach ($workspaces as $workspace) {
            $workspaceName = $workspace->workspaceName;
            $metadata = $this->workspaceService->getWorkspaceMetadata($contentRepositoryId, $workspaceName);
            $workspaceOwner = $metadata->ownerUserId;
            $baseWorkspaceName = $workspace->baseWorkspaceName;
            $isPersonalWorkspace = str_starts_with($workspaceName->value, 'user-');
            $isPrivateWorkspace = $workspaceOwner !== null && !$isPersonalWorkspace;
            $isInternalWorkspace = $baseWorkspaceName !== null && $workspaceOwner === null;

            if ($isPrivateWorkspace || $isInternalWorkspace) {
                $this->createAndOutputWorkspacePreviewToken($workspace->workspaceName);
            }
        }
        $this->persistenceManager->persistAll();
    }

    /**
     * Creates a token and outputs information.
     *
     * @param WorkspaceName $workspaceName
     * @return void
     */
    private function createAndOutputWorkspacePreviewToken(WorkspaceName $workspaceName): void {
        $tokenmetadata = $this->workspacePreviewTokenFactory->create($workspaceName);
        $this->outputLine('Created token for "%s" with hash "%s"', [$workspaceName, $tokenmetadata->getHash()]);
    }
}
