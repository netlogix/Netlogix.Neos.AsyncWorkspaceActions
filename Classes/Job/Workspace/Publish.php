<?php
declare(strict_types=1);

namespace Netlogix\Neos\AsyncWorkspaceActions\Job\Workspace;

use Flowpack\JobQueue\Common\Job\JobInterface;
use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
use GuzzleHttp\Psr7\Uri;
use InvalidArgumentException;
use Neos\ContentRepository\Domain\Repository\WorkspaceRepository;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Neos\Service\PublishingService;
use Neos\Neos\Ui\ContentRepository\Service\NodeService;
use Neos\Neos\Ui\Controller\BackendServiceController;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Error;
use Neos\Neos\Ui\Domain\Model\Feedback\Messages\Success;
use Neos\Neos\Ui\Domain\Model\Feedback\Operations\UpdateNodePreviewUrl;
use Neos\Neos\Ui\Domain\Model\FeedbackCollection;
use Netlogix\Neos\AsyncWorkspaceActions\Domain\Feedback\UpdateWorkspaceInfo;
use Netlogix\Neos\AsyncWorkspaceActions\Domain\Model\Job;
use Netlogix\Neos\AsyncWorkspaceActions\Domain\Repository\JobRepository;
use Netlogix\Neos\AsyncWorkspaceActions\Service\ControllerContextFactory;

/**
 * @see \Neos\Neos\Ui\Controller\BackendServiceController::publishAction()
 */
class Publish implements JobInterface
{

    protected string $requestUri;
    protected string $jobIdentifier;
    protected array $nodeContextPaths;
    protected string $targetWorkspaceName;

    /**
     * @Flow\Transient
     */
    protected FeedbackCollection $feedbackCollection;
    /**
     * @Flow\Transient
     */
    protected JobRepository $jobRepository;
    /**
     * @Flow\Transient
     */
    protected WorkspaceRepository $workspaceRepository;
    /**
     * @Flow\Transient
     */
    protected NodeService $nodeService;
    /**
     * @Flow\Transient
     */
    protected PublishingService $publishingService;
    /**
     * @Flow\Transient
     */
    protected Translator $translator;
    /**
     * @Flow\Transient
     */
    protected PersistenceManagerInterface $persistenceManager;
    /**
     * @Flow\Transient
     */
    protected ControllerContextFactory $controllerContextFactory;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="", package="Neos.Neos.Ui")
     */
    protected array $neosUiSettings = [];

    public function __construct(
        string $jobIdentifier,
        array $nodeContextPaths,
        string $targetWorkspaceName,
        string $requestUri
    ) {
        $this->jobIdentifier = $jobIdentifier;
        $this->nodeContextPaths = $nodeContextPaths;
        $this->targetWorkspaceName = $targetWorkspaceName;
        $this->requestUri = $requestUri;
    }

    public function injectFeedbackCollection(FeedbackCollection $collection): void
    {
        $this->feedbackCollection = $collection;
    }

    public function injectJobRepository(JobRepository $jobRepository): void
    {
        $this->jobRepository = $jobRepository;
    }

    public function injectWorkspaceRepository(WorkspaceRepository $workspaceRepository): void
    {
        $this->workspaceRepository = $workspaceRepository;
    }

    public function injectNodeService(NodeService $nodeService): void
    {
        $this->nodeService = $nodeService;
    }

    public function injectPublishingService(PublishingService $publishingService): void
    {
        $this->publishingService = $publishingService;
    }

    public function injectTranslator(Translator $translator): void
    {
        $this->translator = $translator;
    }

    public function injectPersistenceManagerInterface(PersistenceManagerInterface $persistenceManager): void
    {
        $this->persistenceManager = $persistenceManager;
    }

    public function injectControllerContextFactory(ControllerContextFactory $controllerContextFactory): void
    {
        $this->controllerContextFactory = $controllerContextFactory;
    }

    public function execute(QueueInterface $queue, Message $message): bool
    {
        if (!$this->jobRepository->findByIdentifier($this->jobIdentifier) instanceof Job) {
            throw new InvalidArgumentException(
                sprintf('No job with identifier "%s" found', $this->jobIdentifier),
                1660661997);
        }

        try {
            $this->publish();
        } catch (\Throwable $t) {
            $error = new Error();
            $error->setMessage($t->getMessage());

            $this->feedbackCollection->add($error);
        }

        $controllerContext = $this->controllerContextFactory->buildControllerContext(
            new Uri($this->requestUri)
        );
        $this->feedbackCollection->setControllerContext($controllerContext);

        $job = $this->jobRepository->findByIdentifier($this->jobIdentifier);
        $job->setFeedback($this->feedbackCollection->jsonSerialize());
        $job->setStatus(Job::STATUS_DONE);
        $this->jobRepository->update($job);

        return true;
    }

    private function publish(): void
    {
        $targetWorkspace = $this->workspaceRepository->findOneByName($this->targetWorkspaceName);

        foreach ($this->nodeContextPaths as $contextPath) {
            $node = $this->nodeService->getNodeFromContextPath($contextPath, null, null, true);
            $this->publishingService->publishNode($node, $targetWorkspace);

            if ($node->getNodeType()->isAggregate()) {
                $updateNodePreviewUrl = new UpdateNodePreviewUrl($this->neosUiSettings['nextVersionPreviewBehavior'] ?? false);
                $updateNodePreviewUrl->setNode($node);
                $this->feedbackCollection->add($updateNodePreviewUrl);
            }
        }

        $count = count($this->nodeContextPaths);

        $success = new Success();
        $success->setMessage(
            $this->translator->translateById(
                'changesPublished',
                [$count, $targetWorkspace->getTitle()],
                $count,
                null,
                'Main',
                'Neos.Neos.Ui'
            )
        );
        $this->feedbackCollection->add($success);

        $updateWorkspaceInfo = new UpdateWorkspaceInfo();
        $documentNode = $this->nodeService->getNodeFromContextPath($this->nodeContextPaths[0], null, null, true);
        $updateWorkspaceInfo->setWorkspace(
            $documentNode->getContext()->getWorkspace()
        );
        $this->feedbackCollection->add($updateWorkspaceInfo);

        $this->persistenceManager->persistAll();
    }

    public function getLabel(): string
    {
        return sprintf('Publish to workspace "%s" (%d pending nodes)', $this->targetWorkspaceName,
            count($this->nodeContextPaths));
    }
}
