<?php
declare(strict_types=1);

namespace Netlogix\Neos\AsyncWorkspaceActions\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Entity
 */
class Job
{

    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE = 'done';

    /**
     * @var string
     * @Flow\Identity
     * @ORM\Id
     * @ORM\Column(length=36, options={"fixed": true})
     */
    protected string $identifier;

    /**
     * @var array
     * @ORM\Column(type="json")
     */
    protected array $feedback = [];

    /**
     * @var string
     */
    protected string $status = '';

    public function __construct(
        string $identifier,
        string $status = self::STATUS_PENDING
    ) {
        $this->identifier = $identifier;
        $this->status = $status;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getFeedback(): array
    {
        return $this->feedback;
    }

    public function setFeedback(array $feedback): void
    {
        $this->feedback = $feedback;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

}
