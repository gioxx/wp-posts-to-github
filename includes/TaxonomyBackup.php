<?php

namespace POTOGH;

class TaxonomyBackup
{
    public const OPTION_NAME = 'potogh_taxonomy_backup';
    public const CATEGORIES_PATH = 'wp-categories.json';
    public const TAGS_PATH = 'wp-tags.json';

    public static function buildCategoriesData(): array
    {
        $categories = get_categories(['hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);

        return array_map(static function ($category): array {
            $parent = $category->parent ? get_category($category->parent) : null;

            return [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'parent' => $parent ? $parent->slug : null,
                'count' => (int) $category->count,
            ];
        }, $categories);
    }

    public static function buildTagsData(): array
    {
        $tags = get_tags(['hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);

        return array_map(static function ($tag): array {
            return [
                'name' => $tag->name,
                'slug' => $tag->slug,
                'description' => $tag->description,
                'count' => (int) $tag->count,
            ];
        }, $tags);
    }

    public static function encode(array $data): string
    {
        return wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    public static function currentHash(): string
    {
        return md5(self::encode(self::buildCategoriesData()) . self::encode(self::buildTagsData()));
    }

    public static function storedState(): array
    {
        return get_option(self::OPTION_NAME, [
            'hash' => '',
            'categories' => [],
            'tags' => [],
            'updated_at' => null,
        ]);
    }

    public static function pendingSummary(): array
    {
        $stored = self::storedState();
        $currentCategories = wp_list_pluck(self::buildCategoriesData(), 'slug');
        $currentTags = wp_list_pluck(self::buildTagsData(), 'slug');

        return [
            'has_changes' => self::currentHash() !== $stored['hash'],
            'categories_added' => count(array_diff($currentCategories, $stored['categories'])),
            'categories_removed' => count(array_diff($stored['categories'], $currentCategories)),
            'tags_added' => count(array_diff($currentTags, $stored['tags'])),
            'tags_removed' => count(array_diff($stored['tags'], $currentTags)),
            'updated_at' => $stored['updated_at'],
        ];
    }

    public static function commit(): array
    {
        $settings = Settings::get();
        $client = new GithubClient($settings['token'], $settings['owner_repo'], $settings['branch']);

        $categoriesData = self::buildCategoriesData();
        $tagsData = self::buildTagsData();

        $files = [
            ['path' => self::CATEGORIES_PATH, 'content' => self::encode($categoriesData)],
            ['path' => self::TAGS_PATH, 'content' => self::encode($tagsData)],
        ];

        $result = $client->commitFiles($files, 'Update taxonomy backup (categories/tags)');

        if (!$result['success']) {
            return $result;
        }

        $updatedAt = gmdate('c');

        update_option(self::OPTION_NAME, [
            'hash' => md5(self::encode($categoriesData) . self::encode($tagsData)),
            'categories' => wp_list_pluck($categoriesData, 'slug'),
            'tags' => wp_list_pluck($tagsData, 'slug'),
            'updated_at' => $updatedAt,
        ], false);

        return [
            'success' => true,
            'commit_sha' => $result['commit_sha'],
            'updated_at' => $updatedAt,
        ];
    }
}
