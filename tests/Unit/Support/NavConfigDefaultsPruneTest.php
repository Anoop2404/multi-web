<?php

namespace Tests\Unit\Support;

use App\Support\NavConfigDefaults;
use Illuminate\Support\Collection;
use Tests\TestCase;

class NavConfigDefaultsPruneTest extends TestCase
{
    private function sections(array $types): Collection
    {
        return collect($types)->map(fn (string $type) => (object) ['section_type' => $type]);
    }

    public function test_anchor_is_kept_when_its_section_exists(): void
    {
        $nav = ['items' => [
            ['label' => 'About', 'url' => '/#about-sahodaya', 'children' => []],
        ]];

        $result = NavConfigDefaults::pruneDeadAnchors($nav, $this->sections(['about_sahodaya']));

        $this->assertCount(1, $result['items']);
        $this->assertSame('/#about-sahodaya', $result['items'][0]['url']);
    }

    public function test_anchor_is_dropped_when_its_section_does_not_exist(): void
    {
        $nav = ['items' => [
            ['label' => 'Useful Links', 'url' => '/#useful-links', 'children' => []],
        ]];

        $result = NavConfigDefaults::pruneDeadAnchors($nav, $this->sections(['about_sahodaya']));

        $this->assertCount(0, $result['items']);
    }

    public function test_non_anchor_urls_always_pass_through(): void
    {
        $nav = ['items' => [
            ['label' => 'Circulars', 'url' => '/circulars', 'children' => []],
            ['label' => 'Fest', 'url' => '/fest', 'children' => []],
        ]];

        $result = NavConfigDefaults::pruneDeadAnchors($nav, $this->sections([]));

        $this->assertCount(2, $result['items']);
    }

    public function test_children_are_pruned_recursively(): void
    {
        $nav = ['items' => [
            [
                'label' => 'Academic', 'url' => '/fest', 'children' => [
                    ['label' => 'Live section', 'url' => '/#gallery', 'children' => []],
                    ['label' => 'Dead section', 'url' => '/#academic-quicklinks', 'children' => []],
                    ['label' => 'Real route', 'url' => '/mcq/papers', 'children' => []],
                ],
            ],
        ]];

        $result = NavConfigDefaults::pruneDeadAnchors($nav, $this->sections(['gallery']));

        $this->assertCount(1, $result['items']);
        $this->assertCount(2, $result['items'][0]['children']);
        $this->assertSame('/#gallery', $result['items'][0]['children'][0]['url']);
        $this->assertSame('/mcq/papers', $result['items'][0]['children'][1]['url']);
    }

    public function test_section_type_underscores_map_to_hyphenated_anchors(): void
    {
        $nav = ['items' => [
            ['label' => 'Schools', 'url' => '/#member-schools', 'children' => []],
        ]];

        $result = NavConfigDefaults::pruneDeadAnchors($nav, $this->sections(['member_schools']));

        $this->assertCount(1, $result['items']);
    }
}
