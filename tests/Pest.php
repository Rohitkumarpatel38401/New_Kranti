<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Re-renders a Livewire component from a rendered page through Livewire's real update endpoint,
 * so the panel's persistent middleware (and tenant scoping) runs exactly as in a browser.
 * Returns the raw update response body.
 */
function livewireUpdate(TestCase $test, string $pageHtml, string $componentClass, array $updates = [], array $calls = []): string
{
    preg_match_all('/wire:snapshot="([^"]+)"/', $pageHtml, $matches);

    $snapshot = collect($matches[1])
        ->map(fn (string $raw) => html_entity_decode($raw))
        ->first(fn (string $json) => (json_decode($json, true)['memo']['name'] ?? '') === $componentClass);

    if ($snapshot === null) {
        throw new RuntimeException("Component {$componentClass} not found on the page.");
    }

    return $test->withHeaders(['X-Livewire' => 'true'])
        ->postJson(EndpointResolver::updatePath(), [
            '_token' => csrf_token(),
            'components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => $calls]],
        ])
        ->assertOk()
        ->getContent();
}
