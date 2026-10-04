<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case InProgress = 'in_progress';
    case Qualified = 'qualified';
    case ProposalSent = 'proposal_sent';
    case Won = 'won';
    case Lost = 'lost';
    case Spam = 'spam';
}
