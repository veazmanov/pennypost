<?php

namespace App\Support;

use App\Models\Message;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * A sealed letter or a draft as a file to keep: plain text or PDF. Both
 * formats are built from the same fields so they never disagree.
 */
class LetterExport
{
    public readonly bool $isDraft;

    public readonly CarbonInterface $date;

    public readonly ?string $to;

    public readonly string $from;

    public readonly string $body;

    /** @var list<string> */
    public readonly array $enclosures;

    public function __construct(public readonly Message $message)
    {
        $this->isDraft = $message->is_draft;
        // A draft hasn't been sent, so the last edit is the closest thing to a date.
        $this->date = $this->isDraft ? $message->updated_at : $message->sent_at;
        $this->to = $message->recipient?->name;
        $this->from = (string) $message->sender?->name;
        // Browsers submit textareas with \r\n; the files always use \n.
        $this->body = str_replace(["\r\n", "\r"], "\n", (string) $message->body);
        $this->enclosures = array_values($message->enclosures ?? []);
    }

    public function filename(string $extension): string
    {
        $base = $this->isDraft
            ? 'penny-post-draft-'.$this->date->format('Y-m-d').'-'.$this->message->id
            : 'penny-post-'.$this->date->format('Y-m-d').'-from-'.(Str::slug($this->from) ?: 'unknown');

        return $base.'.'.$extension;
    }

    public function toText(): string
    {
        $lines = array_filter([
            $this->isDraft ? 'Draft' : null,
            'Date: '.$this->date->format('j F Y'),
            $this->to !== null ? 'To: '.$this->to : null,
            'From: '.$this->from,
        ]);

        $text = implode("\n", $lines)."\n\n".$this->body;

        if ($this->enclosures !== []) {
            $text .= "\n\nEnclosed:\n".implode("\n", $this->enclosures);
        }

        return $text."\n";
    }

    public function toPdf(): string
    {
        return Pdf::loadView('exports.letter', ['letter' => $this])
            ->setPaper('a4')
            // Embed only the glyphs the letter uses, not all ~70KB of each cut.
            ->setOption('isFontSubsettingEnabled', true)
            // dompdf scales line-height by the font's ascent + descent (1.205em
            // for ET Book) times this ratio. Undo that so CSS line-height means
            // multiples of the font size, as it does in a browser.
            ->setOption('fontHeightRatio', 1 / 1.205)
            ->output();
    }

    public function download(string $format): Response
    {
        return match ($format) {
            'txt' => response($this->toText(), 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="'.$this->filename('txt').'"',
            ]),
            'pdf' => response($this->toPdf(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$this->filename('pdf').'"',
            ]),
        };
    }
}
