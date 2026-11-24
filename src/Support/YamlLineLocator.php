<?php

declare(strict_types=1);

namespace Grazulex\LaravelModelschema\Support;

/**
 * Maps the keys of a YAML document to the line where they are declared
 *
 * Paths are expressed as dotted strings ("core.fields.title.type") or as an array of
 * segments (useful when a key contains a dot). Sequence items use their zero-based
 * index as segment ("core.fields.title.rules.1").
 *
 * The locator works on block-style YAML (the style used by schema files). Keys declared
 * inside inline flow collections ({...} or [...]) are not indexed: lookups then fall
 * back to the line of the nearest declared parent.
 */
final class YamlLineLocator
{
    /**
     * Matches a mapping key at the start of a line: double-quoted, single-quoted or plain
     */
    private const KEY_PATTERN = '/^(?:"((?:[^"\\\\]|\\\\.)*)"|\'((?:[^\']|\'\')*)\'|([^\s#\'"{}\[\],|>!&*%@`?][^#]*?))\s*:(?=\s|$)/u';

    /**
     * Matches a sequence item indicator ("- ")
     */
    private const SEQUENCE_PATTERN = '/^-(?:\s+|$)/u';

    /**
     * Matches a block scalar indicator ("|", ">", "|-", ">+", "|2", ...) with an optional comment
     */
    private const BLOCK_SCALAR_PATTERN = '/^[|>][0-9+-]*\s*(?:#.*)?$/u';

    /**
     * Line numbers (1-based) indexed by encoded path
     *
     * @var array<string, int>
     */
    private array $lines = [];

    /**
     * Encoded paths in declaration order, with their segments
     *
     * @var array<string, list<string>>
     */
    private array $paths = [];

    public function __construct(string $yamlContent)
    {
        $this->index($yamlContent);
    }

    /**
     * Create a locator for YAML content
     */
    public static function fromString(string $yamlContent): self
    {
        return new self($yamlContent);
    }

    /**
     * Get the line (1-based) where a path is declared
     *
     * When the exact path is not declared and $nearest is true, the line of the closest
     * declared parent is returned. Returns null when nothing matches.
     *
     * @param  string|array<int, string|int>  $path
     */
    public function getLine(string|array $path, bool $nearest = true): ?int
    {
        $segments = $this->segments($path);

        while ($segments !== []) {
            $key = $this->encode($segments);

            if (isset($this->lines[$key])) {
                return $this->lines[$key];
            }

            if (! $nearest) {
                return null;
            }

            array_pop($segments);
        }

        return null;
    }

    /**
     * Check whether a path is explicitly declared in the document
     *
     * @param  string|array<int, string|int>  $path
     */
    public function has(string|array $path): bool
    {
        $segments = $this->segments($path);

        return $segments !== [] && isset($this->lines[$this->encode($segments)]);
    }

    /**
     * Get every indexed path (dotted) with its line number, in declaration order
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->paths as $key => $segments) {
            $result[implode('.', $segments)] = $this->lines[$key];
        }

        return $result;
    }

    /**
     * Normalize a path to a list of segments
     *
     * @param  string|array<int, string|int>  $path
     * @return list<string>
     */
    private function segments(string|array $path): array
    {
        if (is_array($path)) {
            return array_values(array_map(static fn (string|int $segment): string => (string) $segment, $path));
        }

        if ($path === '') {
            return [];
        }

        return explode('.', $path);
    }

    /**
     * Encode segments into a unique array key (dots inside keys stay unambiguous)
     *
     * @param  list<string>  $segments
     */
    private function encode(array $segments): string
    {
        return implode("\0", $segments);
    }

    /**
     * Extract the key from the KEY_PATTERN matches
     *
     * @param  array<int|string, string|null>  $matches
     */
    private function decodeKey(array $matches): string
    {
        if ($matches[1] !== null) {
            return stripcslashes($matches[1]);
        }

        if ($matches[2] !== null) {
            return str_replace("''", "'", $matches[2]);
        }

        return mb_rtrim((string) $matches[3]);
    }

    /**
     * Build the path => line index
     */
    private function index(string $yamlContent): void
    {
        $rawLines = preg_split('/\r\n|\r|\n/', $yamlContent);

        if ($rawLines === false) {
            return;
        }

        /** @var list<array{indent: int, path: list<string>, item: bool}> $stack */
        $stack = [];
        /** @var array<string, int> $counters */
        $counters = [];
        $blockScalarIndent = null;

        foreach ($rawLines as $position => $raw) {
            $lineNumber = $position + 1;
            $trimmed = mb_trim($raw);
            $indent = mb_strlen($raw) - mb_strlen(mb_ltrim($raw, ' '));

            // Skip the content of a literal or folded block scalar
            if ($blockScalarIndent !== null) {
                if ($trimmed === '' || $indent > $blockScalarIndent) {
                    continue;
                }

                $blockScalarIndent = null;
            }

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Document markers start a fresh document
            if ($trimmed === '---' || $trimmed === '...' || str_starts_with($trimmed, '--- ')) {
                $stack = [];
                $counters = [];

                continue;
            }

            $content = mb_substr($raw, $indent);

            if (preg_match(self::SEQUENCE_PATTERN, $content, $matches) === 1) {
                $this->popStack($stack, $indent, true);
                $parent = $stack === [] ? [] : $stack[array_key_last($stack)]['path'];

                $counterKey = $this->encode($parent);
                $itemIndex = $counters[$counterKey] ?? 0;
                $counters[$counterKey] = $itemIndex + 1;

                $path = [...$parent, (string) $itemIndex];
                $this->record($path, $lineNumber);
                $stack[] = ['indent' => $indent, 'path' => $path, 'item' => true];

                $offset = mb_strlen($matches[0]);
                $content = mb_substr($content, $offset);

                if (mb_trim($content) === '') {
                    continue;
                }

                if (preg_match(self::BLOCK_SCALAR_PATTERN, mb_trim($content)) === 1) {
                    $blockScalarIndent = $indent;

                    continue;
                }

                $indent += $offset;
            }

            if (preg_match(self::KEY_PATTERN, $content, $matches, PREG_UNMATCHED_AS_NULL) !== 1) {
                continue;
            }

            $key = $this->decodeKey($matches);

            $this->popStack($stack, $indent, false);
            $parent = $stack === [] ? [] : $stack[array_key_last($stack)]['path'];

            $path = [...$parent, $key];
            $this->record($path, $lineNumber);
            $stack[] = ['indent' => $indent, 'path' => $path, 'item' => false];

            $value = mb_trim(mb_substr($content, mb_strlen($matches[0])));

            if (preg_match(self::BLOCK_SCALAR_PATTERN, $value) === 1) {
                $blockScalarIndent = $indent;
            }
        }
    }

    /**
     * Remove the stack entries that cannot be the parent of a line at the given indent
     *
     * A sequence item may sit at the same indent as its parent key ("key:\n- item"),
     * so for items only previous items at the same indent are removed.
     *
     * @param  list<array{indent: int, path: list<string>, item: bool}>  $stack
     */
    private function popStack(array &$stack, int $indent, bool $isItem): void
    {
        while ($stack !== []) {
            $top = $stack[array_key_last($stack)];

            $isParent = $isItem
                ? ($top['indent'] < $indent || ($top['indent'] === $indent && ! $top['item']))
                : $top['indent'] < $indent;

            if ($isParent) {
                return;
            }

            array_pop($stack);
        }
    }

    /**
     * Record the first declaration of a path
     *
     * @param  list<string>  $path
     */
    private function record(array $path, int $lineNumber): void
    {
        $key = $this->encode($path);

        if (! isset($this->lines[$key])) {
            $this->lines[$key] = $lineNumber;
            $this->paths[$key] = $path;
        }
    }
}
