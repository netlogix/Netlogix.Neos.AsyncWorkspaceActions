<?php
declare(strict_types=1);

namespace Netlogix\Neos\AsyncWorkspaceActions\Aspect;

use Flowpack\JobQueue\Common\Job\JobManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Http\HttpRequestHandlerInterface;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\Utility\Algorithms;
use Neos\Neos\Ui\Controller\BackendServiceController;
use Netlogix\Neos\AsyncWorkspaceActions\Domain\Model\Job;
use Netlogix\Neos\AsyncWorkspaceActions\Domain\Repository\JobRepository;
use Netlogix\Neos\AsyncWorkspaceActions\Job\Workspace\Publish;

/**
 * @Flow\Aspect
 */
class WorkspaceControllerPublishAspect
{

    private const QUEUE_NAME = 'async-workspace-actions';

    /**
     * @var JobManager
     * @Flow\Inject
     */
    protected $jobManager;

    /**
     * @var PersistenceManagerInterface
     * @Flow\Inject
     */
    protected $persistenceManager;

    /**
     * @var JobRepository
     * @Flow\Inject
     */
    protected $jobRepository;

    /**
     * @var Bootstrap
     * @Flow\Inject
     */
    protected $bootstrap;

    /**
     * @var int
     * @Flow\InjectConfiguration(path="nodeThreshold")
     */
    protected int $threshold = 100;

    /**
     * @Flow\Around("method(Neos\Neos\Ui\Controller\BackendServiceController->publishAction())")
     *
     * @param JoinPointInterface $joinPoint
     * @return void
     */
    public function publish(JoinPointInterface $joinPoint)
    {
        $controller = $joinPoint->getProxy();
        assert($controller instanceof BackendServiceController);

        $nodeContextPaths = $joinPoint->getMethodArgument('nodeContextPaths');
        $requestHandler = $this->bootstrap->getActiveRequestHandler();

        if (!($requestHandler instanceof HttpRequestHandlerInterface) || count($nodeContextPaths) < $this->threshold) {
            $joinPoint->getAdviceChain()->proceed($joinPoint);
            return;
        }

        $targetWorkspaceName = $joinPoint->getMethodArgument('targetWorkspaceName');

        $identifier = Algorithms::generateUUID();
        $job = new Job($identifier);

        $publish = new Publish(
            $identifier,
            $nodeContextPaths,
            $targetWorkspaceName,
            (string)$requestHandler->getHttpRequest()->getUri()->withPath('')->withQuery('')->withFragment('')
        );

        $this->jobRepository->add($job);
        $this->persistenceManager->persistAll();

        $this->jobManager->queue(self::QUEUE_NAME, $publish);

        $this->redirectToPoll($controller, $job);
    }

    private function redirectToPoll(BackendServiceController $controller, Job $job): void
    {
        $response = $controller->getControllerContext()->getResponse();

        $uriBuilder = clone $controller
            ->getControllerContext()
            ->getUriBuilder();
        $redirectUri = $uriBuilder
            ->reset()
            ->setFormat('json')
            ->uriFor(
                'poll',
                [
                    'job' => $job->getIdentifier(),
                ],
                'Status',
                'Netlogix.Neos.AsyncWorkspaceActions'
            );
        $response->setStatusCode(303);
        $response->setHttpHeader('Location', $redirectUri);
    }

}
