<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BeritaController extends Controller
{
    private const KEY = 'pengajuan.berita';

    private const CONTENT_TAGS = [
        'a', 'blockquote', 'br', 'em', 'h1', 'h2', 'h3', 'i', 'li', 'ol',
        'p', 's', 'span', 'strong', 'u', 'ul',
    ];

    private const CONTENT_DROP_TAGS = [
        'embed', 'iframe', 'math', 'object', 'script', 'style', 'svg', 'template',
    ];

    private const CATEGORIES = [
        'Prestasi Mahasiswa',
        'Prestasi Dosen',
        'Kunjungan Industri',
        'Info Akademik',
        'Lomba',
        'Magang',
    ];

    private function items(): Collection
    {
        return collect(session(self::KEY, []))->sortByDesc('submitted_at')->values();
    }

    private function find(int $id): array
    {
        $item = $this->items()->firstWhere('id', $id);
        abort_if(! $item, 404);

        return $item;
    }

    private function formData(?array $item = null): array
    {
        if ($item) {
            $item['content'] = $this->sanitizeContent((string) ($item['content'] ?? ''));
        }

        return [
            'item' => $item,
            'categories' => self::CATEGORIES,
            'activities' => app(PengajuanController::class)->activityOptions(),
        ];
    }

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $query = trim($request->string('q')->toString());
        $items = $this->items()->when($status, fn (Collection $items) => $items->where('status', $status))
            ->when($query, fn (Collection $items) => $items->filter(fn ($item) => str_contains(
                mb_strtolower($item['title'].' '.$item['category']),
                mb_strtolower($query)
            )))
            ->values();

        return view('mahasiswa.pengajuan.berita.index', [
            'items' => $items,
            'status' => $status,
            'query' => $query,
            'counts' => $this->items()->countBy('status'),
        ]);
    }

    public function create()
    {
        return view('mahasiswa.pengajuan.berita.form', $this->formData());
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function show(int $id)
    {
        $item = $this->find($id);

        return view('mahasiswa.pengajuan.berita.show', [
            'item' => $item,
            'safeContent' => $this->sanitizeContent((string) ($item['content'] ?? '')),
        ]);
    }

    public function edit(int $id)
    {
        $item = $this->find($id);
        abort_unless(in_array($item['status'], ['Draft', 'Direvisi'], true), 403);

        return view('mahasiswa.pengajuan.berita.form', $this->formData($item));
    }

    public function update(Request $request, int $id)
    {
        $item = $this->find($id);
        abort_unless(in_array($item['status'], ['Draft', 'Direvisi'], true), 403);

        return $this->save($request, $item);
    }

    private function save(Request $request, ?array $item = null)
    {
        $isSubmit = $request->input('intent') === 'submit';
        $required = $isSubmit ? 'required' : 'nullable';
        $content = $request->input('content');
        if (is_string($content)) {
            $request->merge(['content' => $this->sanitizeContent($content)]);
        }

        $activities = app(PengajuanController::class)->activityOptions()
            ->pluck('title')
            ->all();
        $data = $request->validate([
            'intent' => ['required', Rule::in(['draft', 'submit'])],
            'source_type' => [$required, Rule::in(['previous', 'new'])],
            'previous_activity' => [$isSubmit ? 'required_if:source_type,previous' : 'nullable', 'nullable', 'string', 'max:255', Rule::in($activities)],
            'activity_name' => [$isSubmit ? 'required_if:source_type,new' : 'nullable', 'nullable', 'string', 'max:255'],
            'category' => [$required, Rule::in(self::CATEGORIES)],
            'title' => [$required, 'string', 'max:255'],
            'content' => [
                $required,
                'string',
                'max:100000',
                function ($attribute, $value, $fail) {
                    $plainText = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $plainText = str_replace("\xC2\xA0", ' ', $plainText);

                    if (mb_strlen($plainText) > 5000) {
                        $fail('Isi berita maksimal 5.000 karakter.');
                    }
                },
            ],
            'cover' => [$isSubmit && ! ($item['cover'] ?? null) ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $items = $this->items();
        $id = $item['id'] ?? ((int) $items->max('id') + 1);
        $coverPath = $item['cover'] ?? null;

        if ($request->hasFile('cover')) {
            $newPath = $request->file('cover')->store('berita', 'public');
            if ($coverPath) {
                Storage::disk('public')->delete($coverPath);
            }
            $coverPath = $newPath;
        }

        $saved = [
            'id' => $id,
            'source_type' => $data['source_type'] ?? null,
            'previous_activity' => $data['previous_activity'] ?? null,
            'activity_name' => $data['activity_name'] ?? null,
            'category' => $data['category'] ?? null,
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? null,
            'cover' => $coverPath,
            'status' => $isSubmit ? 'Diajukan' : 'Draft',
            'submitted_at' => now()->toIso8601String(),
        ];

        $items = $items->reject(fn ($entry) => $entry['id'] === $id)->push($saved)->values()->all();
        session([self::KEY => $items]);

        return redirect()->route('pengajuan.berita.show', $id)
            ->with('success', $isSubmit ? 'Berita berhasil diajukan.' : 'Berita berhasil disimpan sebagai draft.');
    }

    private function sanitizeContent(string $html): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previousErrorState = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!doctype html><html><body>'.$html.'</body></html>',
                LIBXML_HTML_NODEFDTD | LIBXML_NONET
            );
            $body = $document->getElementsByTagName('body')->item(0);
            if (! $body) {
                return '';
            }

            foreach (iterator_to_array($body->childNodes) as $child) {
                $this->sanitizeContentNode($child);
            }

            $plainText = html_entity_decode(strip_tags($document->saveHTML($body)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (trim(str_replace("\xC2\xA0", ' ', $plainText)) === '') {
                return '';
            }

            $content = '';
            foreach ($body->childNodes as $child) {
                $content .= $document->saveHTML($child);
            }

            return $content;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorState);
        }
    }

    private function sanitizeContentNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMText) {
            return;
        }

        if (! $node instanceof \DOMElement) {
            $node->parentNode?->removeChild($node);

            return;
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, self::CONTENT_DROP_TAGS, true)) {
            $node->parentNode?->removeChild($node);

            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->sanitizeContentNode($child);
        }

        $parent = $node->parentNode;
        if (! in_array($tag, self::CONTENT_TAGS, true)) {
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);

            return;
        }

        $href = $tag === 'a' ? $this->safeLink($node->getAttribute('href')) : null;
        $style = in_array($tag, ['h1', 'h2', 'h3', 'p', 'span'], true)
            ? $this->safeStyle($node->getAttribute('style'))
            : '';

        while ($node->attributes->length > 0) {
            $node->removeAttributeNode($node->attributes->item(0));
        }

        if ($href) {
            $node->setAttribute('href', $href);
            $node->setAttribute('rel', 'noopener noreferrer');
        }
        if ($style !== '') {
            $node->setAttribute('style', $style);
        }
    }

    private function safeLink(string $href): ?string
    {
        $href = trim($href);
        $parts = parse_url($href);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if (in_array($scheme, ['http', 'https'], true)
            && isset($parts['host'])
            && filter_var($href, FILTER_VALIDATE_URL) !== false) {
            return $href;
        }

        if ($scheme === 'mailto'
            && isset($parts['path'])
            && filter_var($parts['path'], FILTER_VALIDATE_EMAIL) !== false) {
            return $href;
        }

        return null;
    }

    private function safeStyle(string $style): string
    {
        $safe = [];
        foreach (explode(';', $style) as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if ($property === 'text-align' && in_array(strtolower($value), ['center', 'justify', 'left', 'right'], true)) {
                $safe[] = $property.': '.strtolower($value);
            } elseif (in_array($property, ['background-color', 'color'], true)) {
                $color = $this->safeEditorColor($value);
                if ($color !== null) {
                    $safe[] = $property.': '.$color;
                }
            }
        }

        return implode('; ', $safe);
    }

    private function safeEditorColor(string $color): ?string
    {
        $color = strtolower(trim($color));
        if (preg_match('/^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/', $color) === 1) {
            return $color;
        }

        if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/', $color, $matches) === 1
            && max((int) $matches[1], (int) $matches[2], (int) $matches[3]) <= 255) {
            return sprintf('rgb(%d, %d, %d)', $matches[1], $matches[2], $matches[3]);
        }

        return null;
    }
}
