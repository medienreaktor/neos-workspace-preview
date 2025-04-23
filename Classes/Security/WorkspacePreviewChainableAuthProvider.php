<?php

namespace Flownative\WorkspacePreview\Security;

use Flownative\TokenAuthentication\Security\Model\HashAndRoles;
use Flownative\TokenAuthentication\Security\Repository\HashAndRolesRepository;
use Medienreaktor\AuthChain\Security\AbstractChainableAuthProvider;
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

class WorkspacePreviewChainableAuthProvider extends AbstractChainableAuthProvider {

    #[InjectConfiguration(path: "authenticationProviderName", package: "Flownative.TokenAuthentication")]
    protected $authenticationProviderName;
    #[Inject]
    protected HashAndRolesRepository $hashAndRolesRepository;

    public function canReadNodesFromWorkspace(WorkspaceName $workspaceName, Privilege $currentValue, callable $next): Privilege {
        if ($this->securityContext->canBeInitialized() !== true) {
            return $next($currentValue);
        }

        $account = $this->securityContext->getAccountByAuthenticationProviderName($this->authenticationProviderName);

        if ($account === null) {
            return $next($currentValue);
        }

        /** @var HashAndRoles $hashAndRoles */
        $hashAndRoles = $this->hashAndRolesRepository->findByIdentifier($account->getAccountIdentifier());

        $settings = $hashAndRoles->getSettings();

        $hashAndRolesWorkspaceName = $settings['workspaceName'];

        if($workspaceName->value === $hashAndRolesWorkspaceName) {
            return Privilege::granted("Previewing");
        }
        return $next($currentValue);
    }

    public function getVisibilityConstraints(WorkspaceName $workspaceName, VisibilityConstraints $currentValue, callable $next): VisibilityConstraints {
        return $next($currentValue);
    }

    public function canExecuteCommand(CommandInterface $command, Privilege $currentValue, callable $next): Privilege {
        return $next($currentValue);
    }

    public function getAuthenticatedUserId(?UserId $currentValue, callable $next): ?UserId {
        return $next($currentValue);
    }
}
