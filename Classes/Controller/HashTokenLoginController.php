<?php
declare(strict_types=1);

namespace Flownative\WorkspacePreview\Controller;

use Flownative\TokenAuthentication\Security\Repository\HashAndRolesRepository;
use Neos\ContentRepository\Core\Feature\Security\Exception\AccessDenied;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindClosestNodeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Workspace\Workspace;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\Argument;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Flow\Security\Authentication\Controller\AbstractAuthenticationController;
use Neos\Neos\Domain\Service\ContentContext;
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

        $possibleNode = $this->getNodeArgumentValue();
        $this->redirectToWorkspace($workspaceName, $possibleNode);
    }

    /**
     * Get a possible node argument from the current request.
     *
     * @return Node|null
     */
    protected function getNodeArgumentValue(): Node|null {
        if (!$this->request->hasArgument('node')) {
            return null;
        }

        $nodeArgument = new Argument('node', Node::class);
        $nodeArgument->setValue($this->request->getArgument('node'));
        return $nodeArgument->getValue();
    }

    /**
     * @param WorkspaceName $workspaceName
     * @param Node|null $nodeToRedirectTo
     * @throws AccessDenied|StopActionException
     */
    protected function redirectToWorkspace(WorkspaceName $workspaceName, Node $nodeToRedirectTo = null): void {
        $nodeInWorkspace = null;
        $contentRepository = $this->contentRepositoryRegistry->get($nodeToRedirectTo->contentRepositoryId);
        $subgraph = $contentRepository->getContentSubgraph($workspaceName, $nodeToRedirectTo->dimensionSpacePoint);
        if ($nodeToRedirectTo instanceof Node) {
            $nodeInWorkspace = $subgraph->findNodeById($nodeToRedirectTo->aggregateId);
        }

        if ($nodeInWorkspace === null) {
            $nodeInWorkspace = $subgraph->findClosestNode($nodeToRedirectTo->aggregateId,
                FindClosestNodeFilter::create(nodeTypes: NodeTypeNameFactory::NAME_SITE));
        }

        $address = NodeAddress::fromNode($nodeInWorkspace)->toJson();

        $this->redirect('preview', 'Frontend\Node', 'Neos.Neos', ['node' => $address]);
    }
}
