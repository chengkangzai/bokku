<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Tags\Tag;

trait HasUserScopedTags
{
    /**
     * Get the tag type for the current user
     */
    public function getUserTagType(): string
    {
        return 'user_' . $this->user_id;
    }

    /**
     * Attach a user-scoped tag
     */
    public function attachUserTag(string|array $tags): void
    {
        $this->attachTags($tags, $this->getUserTagType());
    }

    /**
     * Detach a user-scoped tag
     */
    public function detachUserTag(string|array $tags): void
    {
        $this->detachTags($tags, $this->getUserTagType());
    }

    /**
     * Sync user-scoped tags
     */
    public function syncUserTags(array $tags): void
    {
        $this->syncTagsWithType($tags, $this->getUserTagType());
    }

    /**
     * Get all user-scoped tags for this model
     */
    public function getUserTags()
    {
        return $this->tagsWithType($this->getUserTagType());
    }

    /**
     * Scope query to models with any of the given user tags
     */
    public function scopeWithAnyUserTags(Builder $query, array $tags, int $userId): Builder
    {
        return $query->withAnyTagsOfType($tags, 'user_' . $userId);
    }

    /**
     * Scope query to models with all of the given user tags
     */
    public function scopeWithAllUserTags(Builder $query, array $tags, int $userId): Builder
    {
        $tagType = 'user_' . $userId;
        foreach ($tags as $tag) {
            $query->whereHas('tags', function($q) use ($tag, $tagType) {
                $q->where('name', $tag)->where('type', $tagType);
            });
        }
        return $query;
    }

    /**
     * Get all available tags for the current user
     */
    public static function getAvailableUserTags(int $userId): \Illuminate\Support\Collection
    {
        return Tag::getWithType('user_' . $userId);
    }

    /**
     * Create or find a user-scoped tag
     */
    public static function findOrCreateUserTag(string $name, int $userId): Tag
    {
        return Tag::findOrCreate($name, 'user_' . $userId);
    }
}