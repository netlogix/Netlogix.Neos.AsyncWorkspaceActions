<?php
declare(strict_types=1);

namespace Netlogix\Neos\AsyncWorkspaceActions\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Netlogix\Neos\AsyncWorkspaceActions\Domain\Model\Job;
use Netlogix\Neos\AsyncWorkspaceActions\Domain\Repository\JobRepository;
use function usleep;

class StatusController extends ActionController
{

    /**
     * @var JobRepository
     * @Flow\Inject
     */
    protected $jobRepository;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="polling")
     */
    protected array $pollingConfiguration = [];

    public function pollAction(Job $job, int $count = 1): void
    {
        if ($job->getStatus() !== Job::STATUS_DONE) {
            $this->sleep($count);
            $this->redirect('poll', null, null, [
                'job' => $job,
                'count' => ++$count
            ]);
        }

        $this->redirect('done', null, null, [
            'job' => $job
        ]);
    }

    public function doneAction(Job $job): string
    {
        $feedback = $job->getFeedback();

        $this->persistenceManager->allowObject($job);
        $this->jobRepository->remove($job);

        return json_encode($feedback, JSON_PRETTY_PRINT);
    }

    private function sleep(int $retry): void
    {
        $retryInterval = ($this->pollingConfiguration['retryInterval'] ?? 5);
        if ($this->pollingConfiguration['exponentialBackoff'] ?? false) {
            $backoff = ($this->pollingConfiguration['factor'] ?? 2) ** ($retry - 1) * $retryInterval;
        } else {
            $backoff = $retryInterval;
        }
        $sleeping = (int) ($backoff * 1000 * 1000);
        usleep($sleeping);
    }

}
