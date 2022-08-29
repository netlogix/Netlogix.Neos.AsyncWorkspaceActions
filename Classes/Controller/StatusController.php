<?php
declare(strict_types=1);

namespace Netlogix\Neos\AsyncWorkspaceActions\Controller;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\Controller\ActionController;
use Netlogix\Neos\AsyncWorkspaceActions\Domain\Model\Job;
use Netlogix\Neos\AsyncWorkspaceActions\Domain\Repository\JobRepository;

class StatusController extends ActionController
{

    /**
     * @var JobRepository
     * @Flow\Inject
     */
    protected $jobRepository;

    public function pollAction(Job $job): void
    {
        if ($job->getStatus() !== Job::STATUS_DONE) {
            sleep(5);
            $this->redirect('poll', null, null, ['job' => $job]);
        }

        $this->redirect('done', null, null, ['job' => $job]);
    }

    public function doneAction(Job $job): string
    {
        $feedback = $job->getFeedback();

        $this->persistenceManager->allowObject($job);
        $this->jobRepository->remove($job);

        return json_encode($feedback, JSON_PRETTY_PRINT);
    }

}
