<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemSetComponent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSetsSeeder extends Seeder
{
    /**
     * Curated bundled-item sets. Each entry is keyed by a stable synthetic
     * game_id (>= 1_000_000, reserved for sets — obj.json imports never
     * collide). Components reference real items by slug; if any component
     * slug is missing from the current revision, the set is skipped with a
     * warning so future revisions can ship the components and re-seed.
     *
     * icon_url is the OSRS wiki image URL (PNG). On seed we download it to
     * /public/img/items/{slug}.webp so existing image paths just work.
     */
    public function run(): void
    {
        $sets = [
            // Dragonhide armour (body + chaps + vambraces — 2004-era RSC sets)
            [
                'game_id' => 1_000_001,
                'slug' => 'set_green_dhide',
                'name' => "Green d'hide set",
                'icon_url' => 'https://oldschool.runescape.wiki/images/Green_dragonhide_set.png',
                'components' => ['dragonhide_body', 'dragonhide_chaps', 'dragon_vambraces'],
            ],
            [
                'game_id' => 1_000_002,
                'slug' => 'set_blue_dhide',
                'name' => "Blue d'hide set",
                'icon_url' => 'https://oldschool.runescape.wiki/images/Blue_dragonhide_set.png',
                'components' => ['blue_dragonhide_body', 'blue_dragonhide_chaps', 'blue_dragon_vambraces'],
            ],
            [
                'game_id' => 1_000_003,
                'slug' => 'set_red_dhide',
                'name' => "Red d'hide set",
                'icon_url' => 'https://oldschool.runescape.wiki/images/Red_dragonhide_set.png',
                'components' => ['red_dragonhide_body', 'red_dragonhide_chaps', 'red_dragon_vambraces'],
            ],
            [
                'game_id' => 1_000_004,
                'slug' => 'set_black_dhide',
                'name' => "Black d'hide set",
                'icon_url' => 'https://oldschool.runescape.wiki/images/Black_dragonhide_set.png',
                'components' => ['black_dragonhide_body', 'black_dragonhide_chaps', 'black_dragon_vambraces'],
            ],

            // God armour (plate + legs + kite + full helm)
            [
                'game_id' => 1_000_010,
                'slug' => 'set_saradomin_armour',
                'name' => 'Saradomin armour set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Saradomin_armour_set_(lg).png',
                'components' => [
                    'rune_platebody_saradomin',
                    'rune_platelegs_saradomin',
                    'rune_kiteshield_saradomin',
                    'rune_full_helm_saradomin',
                ],
            ],
            [
                'game_id' => 1_000_011,
                'slug' => 'set_zamorak_armour',
                'name' => 'Zamorak armour set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Zamorak_armour_set_(lg).png',
                'components' => [
                    'rune_platebody_zamorak',
                    'rune_platelegs_zamorak',
                    'rune_kiteshield_zamorak',
                    'rune_full_helm_zamorak',
                ],
            ],
            [
                'game_id' => 1_000_012,
                'slug' => 'set_guthix_armour',
                'name' => 'Guthix armour set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Guthix_armour_set_(lg).png',
                'components' => [
                    'rune_platebody_guthix',
                    'rune_platelegs_guthix',
                    'rune_kiteshield_guthix',
                    'rune_full_helm_guthix',
                ],
            ],

            // Rune trimmed / gold
            [
                'game_id' => 1_000_020,
                'slug' => 'set_rune_trim',
                'name' => 'Rune (t) armour set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Rune_trimmed_set_(lg).png',
                'components' => [
                    'rune_platebody_trim',
                    'rune_platelegs_trim',
                    'rune_kiteshield_trim',
                    'rune_full_helm_trim',
                ],
            ],
            [
                'game_id' => 1_000_021,
                'slug' => 'set_rune_gold',
                'name' => 'Rune (g) armour set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Rune_gold-trimmed_set_(lg).png',
                'components' => [
                    'rune_platebody_gold',
                    'rune_platelegs_gold',
                    'rune_kiteshield_gold',
                    'rune_full_helm_gold',
                ],
            ],

            // Adamant trimmed / gold
            [
                'game_id' => 1_000_030,
                'slug' => 'set_adamant_trim',
                'name' => 'Adamant (t) armour set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Adamant_trimmed_set_(lg).png',
                'components' => [
                    'adamant_platebody_trim',
                    'adamant_platelegs_trim',
                    'adamant_kiteshield_trim',
                    'adamant_full_helm_trim',
                ],
            ],
            [
                'game_id' => 1_000_031,
                'slug' => 'set_adamant_gold',
                'name' => 'Adamant (g) armour set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Adamant_gold-trimmed_set_(lg).png',
                'components' => [
                    'adamant_platebody_gold',
                    'adamant_platelegs_gold',
                    'adamant_kiteshield_gold',
                    'adamant_full_helm_gold',
                ],
            ],

            // Black trimmed / gold
            [
                'game_id' => 1_000_040,
                'slug' => 'set_black_trim',
                'name' => 'Black (t) armour set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Black_trimmed_set_(lg).png',
                'components' => [
                    'black_platebody_trim',
                    'black_platelegs_trim',
                    'black_kiteshield_trim',
                    'black_full_helm_trim',
                ],
            ],
            [
                'game_id' => 1_000_041,
                'slug' => 'set_black_gold',
                'name' => 'Black (g) armour set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Black_gold-trimmed_set_(lg).png',
                'components' => [
                    'black_platebody_gold',
                    'black_platelegs_gold',
                    'black_kiteshield_gold',
                    'black_full_helm_gold',
                ],
            ],

            // Dwarf cannon package
            [
                'game_id' => 1_000_050,
                'slug' => 'set_dwarf_cannon',
                'name' => 'Dwarf cannon set',
                'icon_url' => 'https://oldschool.runescape.wiki/images/Dwarf_cannon_set.png',
                'components' => ['twpart1', 'twpart2', 'twpart3', 'twpart4'],
            ],
        ];

        DB::transaction(function () use ($sets) {
            foreach ($sets as $s) {
                $components = Item::whereIn('slug', $s['components'])->get()->keyBy('slug');
                $missing = array_diff($s['components'], $components->keys()->all());

                if (! empty($missing)) {
                    $this->command?->warn("Skipping set {$s['slug']}: missing components " . implode(', ', $missing));

                    continue;
                }

                $set = Item::updateOrCreate(
                    ['game_id' => $s['game_id']],
                    [
                        'slug' => $s['slug'],
                        'name' => $s['name'],
                        'cost' => (int) $components->sum('cost'),
                        'description' => 'Bundled item set.',
                        'is_active' => true,
                        'is_listable' => true,
                        'is_set' => true,
                    ]
                );

                ItemSetComponent::where('set_item_id', $set->id)->delete();

                foreach ($s['components'] as $slug) {
                    ItemSetComponent::create([
                        'set_item_id' => $set->id,
                        'item_id' => $components[$slug]->id,
                        'quantity' => 1,
                    ]);
                }

                $this->downloadIcon($set->slug, $s['icon_url']);

                $this->command?->info("Seeded set: {$s['name']} ({$components->count()} components)");
            }
        });
    }

    /**
     * Fetch the wiki PNG and convert to WebP at /img/items/{slug}.webp.
     * Skips the download if the destination already exists so re-runs are
     * cheap; delete the file to force a refresh.
     */
    private function downloadIcon(string $setSlug, string $iconUrl): void
    {
        $dst = public_path("img/items/{$setSlug}.webp");

        if (is_file($dst)) {
            return;
        }

        $tmpPng = tempnam(sys_get_temp_dir(), 'lc_set_') . '.png';

        $ctx = stream_context_create([
            'http' => ['user_agent' => 'LostCityMarket/1.0 item-sets-seeder'],
        ]);
        $png = @file_get_contents($iconUrl, false, $ctx);

        if ($png === false || strlen($png) < 100) {
            $this->command?->warn("Could not fetch icon for {$setSlug} from {$iconUrl}");
            return;
        }

        file_put_contents($tmpPng, $png);

        $magick = trim((string) shell_exec('command -v magick'));
        $convert = trim((string) shell_exec('command -v convert'));
        $binary = $magick !== '' ? $magick : $convert;

        if ($binary === '') {
            $this->command?->warn('No imagemagick binary found; cannot convert PNG to WebP');
            @unlink($tmpPng);
            return;
        }

        $cmd = escapeshellarg($binary) . ' ' . escapeshellarg($tmpPng) . ' ' . escapeshellarg($dst) . ' 2>&1';
        $out = shell_exec($cmd);

        @unlink($tmpPng);

        if (! is_file($dst)) {
            $this->command?->warn("Icon conversion failed for {$setSlug}: {$out}");
        }
    }
}
