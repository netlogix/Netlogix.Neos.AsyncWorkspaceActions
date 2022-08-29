<?php
declare(strict_types=1);

namespace Netlogix\Neos\AsyncWorkspaceActions\Domain\Feedback;

use Neos\Flow\Mvc\Controller\ControllerContext;
use Neos\Neos\Ui\Domain\Model\Feedback\Operations\UpdateWorkspaceInfo as NeosUpdateWorkspaceInfo;

class UpdateWorkspaceInfo extends NeosUpdateWorkspaceInfo
{

    /**
     * Serialize the payload for this feedback
     *
     * @return mixed
     */
    public function serializePayload(ControllerContext $controllerContext)
    {
        $workspace = $this->getWorkspace();
        $baseWorkspace = $workspace->getBaseWorkspace();
        return [
            'name' => $workspace->getName(),
            'publishableNodes' => $this->workspaceService->getPublishableNodeInfo($workspace),
            'baseWorkspace' => $baseWorkspace->getName(),
            'readOnly' => false // FIXME: This should actually check if the user is allowed to edit
        ];
    }

}
