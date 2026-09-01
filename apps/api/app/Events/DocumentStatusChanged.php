<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocumentStatusChanged
{
    use Dispatchable, SerializesModels;

    public Document $document;
    public string $status;
    public ?string $note;

    /**
     * Create a new event instance.
     */
    public function __construct(Document $document, string $status, ?string $note = null)
    {
        $this->document = $document;
        $this->status = $status;
        $this->note = $note;
    }
}
