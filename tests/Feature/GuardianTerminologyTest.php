<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GuardianTerminologyTest extends TestCase
{
    public function test_user_facing_copy_uses_guardian_terminology(): void
    {
        $offenders = [];

        foreach ($this->filesToScan() as $file) {
            $text = str_replace("\r\n", "\n", File::get($file->getPathname()));
            $copy = str_ends_with($file->getFilename(), '.blade.php')
                ? $this->bladeStaticCopy($text) . "\n" . $this->phpStringLiterals($this->bladeLiteralSource($text))
                : $this->phpStringLiterals($text);

            if (preg_match('/\bparents?\b|\bparental\b/i', $copy)) {
                $offenders[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders);
    }

    private function filesToScan(): array
    {
        return array_merge(
            File::allFiles(resource_path('views')),
            File::allFiles(app_path('Notifications')),
            File::allFiles(app_path('Http/Controllers')),
            File::allFiles(app_path('Http/Requests')),
        );
    }

    private function bladeStaticCopy(string $text): string
    {
        $text = preg_replace('/@php.*?@endphp/s', ' ', $text);
        $text = preg_replace('/@js\(.*?\)/s', ' ', $text);
        $text = preg_replace('/route\(.*?\)/s', ' ', $text);
        $text = preg_replace('/\sdata-testid=(["\']).*?\1/s', ' ', $text);
        $text = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', ' ', $text);
        $text = preg_replace('/<\?php.*?\?>/s', ' ', $text);
        $text = preg_replace('/<script\b.*?<\/script>/is', ' ', $text);
        $text = preg_replace('/^\s*@.*$/m', ' ', $text);

        $text = preg_replace('/\bparents?\b/', ' ', $text ?? '');
        $text = preg_replace('/\b[a-z0-9_]*parent[A-Z0-9_][A-Za-z0-9_]*\b/', ' ', $text ?? '');

        return $text ?? '';
    }

    private function phpStringLiterals(string $text): string
    {
        preg_match_all('/([\'"])(?:(?!\1).)*\b(?:parents?|parental)\b(?:(?!\1).)*\1/i', $text, $matches);

        return collect($matches[0] ?? [])
            ->reject(fn (string $literal) => $this->isInternalParentToken(trim($literal, '\'"')))
            ->implode("\n");
    }

    private function bladeLiteralSource(string $text): string
    {
        return preg_replace('/\s(?:x-|@|:)[\w:.-]+=(["\']).*?\1/s', ' ', $text) ?? '';
    }

    private function isInternalParentToken(string $literal): bool
    {
        return $literal === 'parent'
            || $literal === 'parents'
            || str_contains($literal, '$parent')
            || str_contains($literal, '->parent')
            || str_contains($literal, "route('")
            || str_contains($literal, 'request()->routeIs')
            || str_contains($literal, 'data-testid=')
            || $literal === 'access parent dashboard'
            || preg_match('/\A[a-z0-9_]*parent[A-Z0-9_][A-Za-z0-9_]*\z/', $literal)
            || (str_contains($literal, ':') && preg_match('/\A\S*parent\S*\z/i', $literal))
            || (str_contains($literal, ',') && preg_match('/\A\S*parent\S*\z/i', $literal))
            || (
                preg_match('/[._\/*-]/', $literal)
                && preg_match('/\A[a-z0-9_.\/*-]*parent[a-z0-9_.\/*-]*\z/i', $literal)
            );
    }
}
