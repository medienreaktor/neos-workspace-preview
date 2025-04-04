<?php
declare(strict_types=1);

namespace Flownative\WorkspacePreview\Controller;

use Flownative\TokenAuthentication\Security\Repository\HashAndRolesRepository;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\Security\Exception\AccessDenied;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindClosestNodeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\Workspace;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\Argument;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Security\Authentication\Controller\AbstractAuthenticationController;
use Neos\Neos\Domain\Model\WorkspaceRole;
use Neos\Neos\Domain\Model\WorkspaceRoleAssignment;
use Neos\Neos\Domain\Repository\WorkspaceMetadataAndRoleRepository;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;

/**
 *
 */
class HashTokenLoginController extends AbstractAuthenticationController {
    /**
     * @Flow\Inject
     * @var HashAndRolesRepository
     */
    protected $hashAndRolesRepository;
    /**
     * @Flow\Inject
     * @var ContentRepositoryRegistry
     */
    protected $contentRepositoryRegistry;

    /**
     * @Flow\Inject
     * @var WorkspaceMetadataAndRoleRepository
     */
    protected $workspaceMetadataAndRoleRepository;

    /**
     * @param ActionRequest|null $originalRequest
     */
    protected function onAuthenticationSuccess(ActionRequest $originalRequest = null) {
        $tokenHash = $this->request->getArgument('_authenticationHashToken');
        $token = $this->hashAndRolesRepository->findByIdentifier($tokenHash);
        if (!$token) {
            return;
        }

        $workspaceName = $token->getSettings()['workspaceName'] ?? '';
        if (empty($workspaceName)) {
            return;
        }
        $workspaceName = WorkspaceName::fromString($workspaceName);

        $contentRepositoryId = ContentRepositoryId::fromString("default");

//        $this->workspaceMetadataAndRoleRepository->assignWorkspaceRole($contentRepositoryId, $workspaceName,
//            WorkspaceRoleAssignment::createForGroup("Flownative.WorkspacePreview:WorkspacePreviewer", WorkspaceRole::VIEWER));
//

        $nodeAggregateId = NodeAggregateId::fromString($this->request->getArgument("aggregateId"));
        $dimensionSpacePoint = DimensionSpacePoint::fromJsonString($this->request->getArgument("dimensionSpacePoint"));
        $this->redirectToWorkspace($contentRepositoryId, $workspaceName, $nodeAggregateId, $dimensionSpacePoint);

    }


    /**
     * @param WorkspaceName $workspaceName
     * @param Node|null $nodeToRedirectTo
     * @throws AccessDenied|StopActionException
     */
    protected function redirectToWorkspace(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, NodeAggregateId $nodeToRedirectTo = null, DimensionSpacePoint $dimensionSpacePoint = null): void {
        $nodeInWorkspace = null;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $subgraph = $contentRepository->getContentSubgraph($workspaceName, $dimensionSpacePoint);
        if ($nodeToRedirectTo instanceof NodeAggregateId) {
            $nodeInWorkspace = $subgraph->findNodeById($nodeToRedirectTo);
        }

        if ($nodeInWorkspace === null) {
            $nodeInWorkspace = $subgraph->findClosestNode($nodeToRedirectTo,
                FindClosestNodeFilter::create(nodeTypes: NodeTypeNameFactory::NAME_SITE));
        }

        $address = NodeAddress::fromNode($nodeInWorkspace)->toJson();

        $this->redirect('preview', 'Frontend\Node', 'Neos.Neos', ['node' => $address]);
    }
}
