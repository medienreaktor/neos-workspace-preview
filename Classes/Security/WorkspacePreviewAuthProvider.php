<?php

namespace Flownative\WorkspacePreview\Security;

use Flownative\TokenAuthentication\Security\Model\HashAndRoles;
use Flownative\TokenAuthentication\Security\Repository\HashAndRolesRepository;
use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\Feature\Security\AuthProviderInterface;
use Neos\ContentRepository\Core\Feature\Security\Dto\Privilege;
use Neos\ContentRepository\Core\Feature\Security\Dto\UserId;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Neos\Domain\Service\UserService;
use Neos\Neos\Security\Authorization\ContentRepositoryAuthorizationService;
use Neos\Neos\Security\ContentRepositoryAuthProvider\ContentRepositoryAuthProvider;

class WorkspacePreviewAuthProvider implements AuthProviderInterface {

    protected ContentRepositoryAuthProvider $contentRepositoryAuthProvider;

    #[InjectConfiguration(path: "authenticationProviderName", package: "Flownative.TokenAuthentication")]
    protected $authenticationProviderName;

    #[Inject]
    protected HashAndRolesRepository $hashAndRolesRepository;

    public function __construct(
        private ContentRepositoryId $contentRepositoryId,
        private UserService $userService,
        private ContentGraphReadModelInterface $contentGraphReadModel,
        private ContentRepositoryAuthorizationService $authorizationService,
        private SecurityContext $securityContext,
    ) {
        $this->contentRepositoryAuthProvider = new ContentRepositoryAuthProvider($this->contentRepositoryId, $this->userService, $this->contentGraphReadModel, $this->authorizationService, $this->securityContext);
    }

    public function getAuthenticatedUserId(): ?UserId {
        return $this->contentRepositoryAuthProvider->getAuthenticatedUserId();
    }

    public function canReadNodesFromWorkspace(WorkspaceName $workspaceName): Privilege {
        if ($this->securityContext->canBeInitialized() !== true) {
            return $this->contentRepositoryAuthProvider->canReadNodesFromWorkspace($workspaceName);
        }

        $account = $this->securityContext->getAccountByAuthenticationProviderName($this->authenticationProviderName);
        if ($account === null) {
            return $this->contentRepositoryAuthProvider->canReadNodesFromWorkspace($workspaceName);
        }

        /** @var HashAndRoles $hashAndRoles */
        $hashAndRoles = $this->hashAndRolesRepository->findByIdentifier($account->getAccountIdentifier());

        $settings = $hashAndRoles->getSettings();

        $hashAndRolesWorkspaceName = $settings['workspaceName'];

        if($workspaceName->value === $hashAndRolesWorkspaceName) {
            return Privilege::granted("Previewing");
        }
        return $this->contentRepositoryAuthProvider->canReadNodesFromWorkspace($workspaceName);
    }

    public function getVisibilityConstraints(WorkspaceName $workspaceName): VisibilityConstraints {
        return $this->contentRepositoryAuthProvider->getVisibilityConstraints($workspaceName);
    }

    public function canExecuteCommand(CommandInterface $command): Privilege {
        return $this->contentRepositoryAuthProvider->canExecuteCommand($command);
    }
}
