<?php

namespace App\Console\Commands;

use App\Support\SupportChatRetention;
use Illuminate\Console\Command;

class PruneSupportChatMessages extends Command
{
    protected $signature = 'support-chat:prune';

    protected $description = 'Delete client support chat messages (and their attachment files) older than filmspec.support_chat_retention_days';

    public function handle(): int
    {
        $days = SupportChatRetention::retentionDays();
        $deleted = SupportChatRetention::prune();

        if ($deleted === 0) {
            $this->info("No support chat messages older than $days days.");
        } else {
            $this->info("Pruned $deleted support chat message(s) (and any attachments) older than $days days.");
        }

        return self::SUCCESS;
    }
}
