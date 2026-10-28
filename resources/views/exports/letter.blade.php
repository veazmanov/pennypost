<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $letter->filename('pdf') }}</title>
    {{-- dompdf can't embed WOFF, so the PDF uses TTF copies of the same three cuts the site serves. --}}
    <style>
        @font-face {
            font-family: "et-book";
            src: url("file://{{ resource_path('fonts/et-book/et-book-roman-line-figures.ttf') }}") format("truetype");
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: "et-book";
            src: url("file://{{ resource_path('fonts/et-book/et-book-display-italic-old-style-figures.ttf') }}") format("truetype");
            font-weight: normal;
            font-style: italic;
        }
        @font-face {
            font-family: "et-book";
            src: url("file://{{ resource_path('fonts/et-book/et-book-bold-line-figures.ttf') }}") format("truetype");
            font-weight: bold;
            font-style: normal;
        }

        @page { size: A4; margin: 30mm 28mm; }

        body {
            margin: 0;
            background: #fff;
            color: #000;
            font-family: "et-book", serif;
            font-size: 13pt;
            line-height: 1.45;
        }

        p { margin: 0; }
        .date { text-align: right; }
        .to { margin-top: 18pt; text-align: center; }
        .to .name { margin-left: 8pt; text-transform: uppercase; }
        .body { margin-top: 24pt; white-space: pre-wrap; }
        .from { margin-top: 24pt; text-align: right; }
        .enclosures { margin-top: 28pt; font-size: 10.5pt; }
        .enclosures .label { font-size: 9pt; letter-spacing: 0.08em; }
        .enclosures .url { margin-top: 4pt; word-wrap: break-word; }
    </style>
</head>
<body>
    <p class="date">
        @if ($letter->isDraft)<em>{{ __('Draft') }}</em> &middot; @endif{{ $letter->date->format('j F Y') }}
    </p>

    @if ($letter->to !== null)
        <p class="to"><em>{{ __('To') }}</em><span class="name">{{ $letter->to }}</span></p>
    @endif

    <div class="body">{{ $letter->body }}</div>

    <p class="from">[{{ $letter->from }}]</p>

    @if ($letter->enclosures !== [])
        <div class="enclosures">
            <p class="label">{{ __('ENCLOSED') }}</p>
            @foreach ($letter->enclosures as $url)
                <p class="url">{{ $url }}</p>
            @endforeach
        </div>
    @endif
</body>
</html>
