<?php namespace App\Events;

use App\Models\Invitation;
use Illuminate\Queue\SerializesModels;

/**
 * Class QuoteInvitationWasEmailed
 */
class QuoteInvitationWasEmailed extends Event
{
    use SerializesModels;

    /**
     * @var Invitation
     */
    public $invitation;

    /**
     * @var String
     */
    public $notes;

    /**
     * Create a new event instance.
     *
     * @param Invitation $invitation
     */
    public function __construct(Invitation $invitation, $notes)
    {
        $this->invitation = $invitation;
        $this->notes = $notes;
    }

}
