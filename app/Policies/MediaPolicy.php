<?php

namespace App\Policies;

use App\Models\Admin;
use Palgoal\MediaLibrary\Models\Media;

/**
 * AD-UI-CAT-M2-A (finding M1-F3): replaces the package default policy, which allowed every action to any
 * signed-in user. Registered through config('media-library.policy'). Each action needs its own media.*
 * ability; super admins keep their bypass through the application's Gate::before (AppServiceProvider).
 *
 * The library is shared (decision Q-M4): an ability applies to every media item, not only one's own uploads.
 */
class MediaPolicy
{
    /** The library page, the JSON list. */
    public function viewAny($user): bool
    {
        return $this->allows($user, 'media.view');
    }

    /** One item (show / edit endpoints). */
    public function view($user, Media $media): bool
    {
        return $this->allows($user, 'media.view');
    }

    public function create($user): bool
    {
        return $this->allows($user, 'media.create');
    }

    /** Metadata only; the package never replaces the stored file. */
    public function update($user, Media $media): bool
    {
        return $this->allows($user, 'media.edit');
    }

    /** Single and bulk delete (the package authorizes every item before deleting any). */
    public function delete($user, Media $media): bool
    {
        return $this->allows($user, 'media.delete');
    }

    private function allows($user, string $ability): bool
    {
        return $user instanceof Admin && $user->hasAbility($ability);
    }
}
