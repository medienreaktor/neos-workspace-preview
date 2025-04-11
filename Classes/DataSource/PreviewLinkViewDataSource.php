<?php
declare(strict_types=1);

namespace Flownative\WorkspacePreview\DataSource;

use Flownative\TokenAuthentication\Security\Model\HashAndRoles;
use Flownative\TokenAuthentication\Security\Repository\HashAndRolesRepository;
use GuzzleHttp\Psr7\ServerRequest;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindClosestNodeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Helper\UriHelper;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Neos\Neos\Domain\Model\SiteNodeName;
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;
use Neos\Neos\Domain\Service\WorkspaceService;
use Neos\Neos\FrontendRouting\NodeUriBuilderFactory;
use Neos\Neos\FrontendRouting\Options;
use Neos\Neos\FrontendRouting\SiteDetection\SiteDetectionResult;
use Neos\Neos\Service\DataSource\DataSourceInterface;
use Neos\Neos\Service\UserService;
use Psr\Http\Message\UriInterface;

/**
 *
 */
class PreviewLinkViewDataSource implements DataSourceInterface {
    private const ERROR_NONE = 'none';
    private const ERROR_MISSING_NODE = 'missingNode';
    private const ERROR_PUBLIC_WORKSPACE = 'publicWorkspace';
    private const ERROR_MISSING_TOKEN = 'missingToken';

    /**
     * @Flow\Inject
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @Flow\Inject
     * @var NodeUriBuilderFactory
     */
    protected $nodeUriBuilderFactory;

    /**
     * @var ControllerContext
     */
    protected $controllerContext;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;
    /**
     * @Flow\Inject
     * @var WorkspaceService
     */
    protected $workspaceService;
    /**
     * @Flow\Inject
     * @var ContentRepositoryRegistry
     */
    protected $contentRepositoryRegistry;

    /**
     * @Flow\Inject
     * @var HashAndRolesRepository
     */
    protected $hashAndRolesRepository;

    /**
     * @param ControllerContext $controllerContext
     */
    public function setControllerContext(ControllerContext $controllerContext): void {
        $this->controllerContext = $controllerContext;
    }

    public static function getIdentifier(): string {
        return 'PreviewLinkView';
    }

    /**
     * @param Node|null $node
     * @param array $arguments
     * @return array
     * @throws MissingActionNameException
     */
    public function getData(Node $node = null, array $arguments = []): array {
        $link = $this->getLink($node, $error);
        return [
            'data' => [
                'link' => $link,
                'error' => $error,
            ]
        ];
    }

    /**
     * @param Node|null $node
     * @param string|null $error
     * @return string
     * @throws MissingActionNameException
     */
    protected function getLink(Node $node = null, string &$error = null): string {
        if ($node === null) {
            $error = self::ERROR_MISSING_NODE;
            return '';
        }
        $currentUser = $this->userService->getBackendUser();
        $contentRepositoryId = $node->contentRepositoryId;
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $personalWorkspace = $this->workspaceService->getPersonalWorkspaceForUser($contentRepositoryId, $currentUser->getId());

        $baseWorkspace = $contentRepository->findWorkspaceByName($personalWorkspace->baseWorkspaceName);

        $baseWorkspaceMetadata = $this->workspaceService->getWorkspaceMetadata($contentRepositoryId, $baseWorkspace->workspaceName);
        if ($baseWorkspace->isRootWorkspace() && $baseWorkspaceMetadata->ownerUserId == null) {
            $error = self::ERROR_PUBLIC_WORKSPACE;
            return '';
        }

        $hashAndRoles = $this->getHashTokenForWorkspace($baseWorkspace->workspaceName->value);
        if ($hashAndRoles === null) {
            $error = self::ERROR_MISSING_TOKEN;
            return '';
        }

        $address = NodeAddress::fromNode($node)->toJson();

        $actionRequest = $this->createActionRequest($node);

        $this->uriBuilder->setRequest($actionRequest->getMainRequest());
        $this->uriBuilder->setCreateAbsoluteUri(true);
        $url = $this->uriBuilder->uriFor("authenticate",

            [
                '_authenticationHashToken' => $hashAndRoles->getHash(),
                'node' => $address
            ],
            "HashTokenLogin",
            "Flownative.WorkspacePreview",
        );
        $error = self::ERROR_NONE;
        return $url;
    }

    /**
     * @param string $workspaceName
     * @return HashAndRoles
     */
    protected function getHashTokenForWorkspace(string $workspaceName): ?HashAndRoles {
        $possibleTokens = $this->hashAndRolesRepository->findByRoles(['Flownative.WorkspacePreview:WorkspacePreviewer']);

        return array_reduce($possibleTokens->toArray(), static function ($foundToken, HashAndRoles $currentToken) use ($workspaceName) {
            $currentWorkspaceName = $currentToken->getSettings()['workspaceName'] ?? null;
            if ($currentWorkspaceName === $workspaceName) {
                return $currentToken;
            }

            return $foundToken;
        }, null);
    }



     /**
 * Create a action request
 *
 * @param string|Node|null $value
 * @return ActionRequest
 */
    public function createActionRequest(string|Node $value = null): ActionRequest {
        $domain = null;
        if (is_string($value)) {
            $domain = $value;
        }
        if ($value instanceof Node) {
            $domain = $this->getDomain($value);
        }
        if (!$domain) {
            $domain = 'http://domain.dummy';
        }

        $subgraph = $this->contentRepositoryRegistry->subgraphForNode($value);
        $siteNode = $subgraph->findClosestNode($value->aggregateId, FindClosestNodeFilter::create(nodeTypes: NodeTypeNameFactory::NAME_SITE));
        $siteNodeName = $siteNode->name;
        $siteNodeName = SiteNodeName::fromNodeName($siteNodeName);
        $httpRequest = new ServerRequest('GET', $domain);
        $httpRequest = (SiteDetectionResult::create($siteNodeName, $value->contentRepositoryId))->storeInRequest($httpRequest);
        $actionRequest = ActionRequest::fromHttpRequest($httpRequest);
        $actionRequest->setFormat('html');

        return $actionRequest;
    }
    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;
    public function getDomain(Node $node): string {
        try {
            $subgraph = $this->contentRepositoryRegistry->subgraphForNode($node);
            $siteNode = $subgraph->findClosestNode($node->aggregateId, FindClosestNodeFilter::create(nodeTypes: NodeTypeNameFactory::NAME_SITE));
            $site = $this->siteRepository->findSiteBySiteNode($siteNode);
            if ($site && $site->isOnline()) {
                $domain = $site->getPrimaryDomain();
                if ($domain && $domain->getActive()) {
                    $uri = $domain->__toString();
                    if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
                        return $uri;
                    }
                    return 'https://' . $uri;
                }
            }

        } catch (\Exception $e) {

        }

        return '';
    }

}
