<?php

namespace Tests;

use Coyote\Forum;
use Coyote\Group;
use Coyote\Permission;
use Coyote\Topic;
use Coyote\User;
use Illuminate\Contracts\Console\Kernel;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function createUserWithGroup(): User
    {
        $user = factory(User::class)->create();

        /** @var Group $group */
        $group = factory(Group::class)->create();
        $group->users()->attach($user->id);

        return $user;
    }

    public function createTopic(int $forumId, array $data = []): Topic
    {
        return factory(Topic::class)->create(array_merge($data, ['forum_id' => $forumId]));
    }

    public function createForum(array $data = [], int $groupId = null): Forum
    {
        if ($groupId) {
            $data['is_prohibited'] = true;
        }

        $forum = factory(Forum::class)->create($data);

        if ($groupId) {
            $forum->access()->create(['group_id' => $groupId]);
        }

        return $forum;
    }

    public function grantAdminAccess(User $user)
    {
        $group = factory(Group::class)->create();

        $group->users()->attach($user->id);

        $permissions = Permission::all();

        foreach ($permissions as $permission) {
            $group->permissions()->attach($permission->id, ['value' => 1]);
        }
    }
}
