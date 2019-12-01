<?php

namespace Coyote\Http\Controllers;

use Coyote\Http\Resources\ActivityResource as ActivityResource;
use Coyote\Repositories\Contracts\ActivityRepositoryInterface as ActivityRepository;
use Coyote\Repositories\Contracts\MicroblogRepositoryInterface as MicroblogRepository;
use Coyote\Repositories\Contracts\ReputationRepositoryInterface as ReputationRepository;
use Coyote\Repositories\Contracts\TopicRepositoryInterface as TopicRepository;
use Coyote\Repositories\Contracts\WikiRepositoryInterface as WikiRepository;
use Coyote\Repositories\Criteria\EagerLoading;
use Coyote\Repositories\Criteria\Forum\SkipHiddenCategories;
use Coyote\Repositories\Criteria\Microblog\LoadComments;
use Coyote\Repositories\Criteria\Microblog\OrderByScore;
use Coyote\Repositories\Criteria\Topic\OnlyThoseWithAccess as OnlyThoseTopicsWithAccess;
use Coyote\Repositories\Criteria\Forum\OnlyThoseWithAccess as OnlyThoseForumsWithAccess;
use Coyote\Services\Session\Renderer;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeController extends Controller
{
    /**
     * @var MicroblogRepository
     */
    protected $microblog;

    /**
     * @var ReputationRepository
     */
    protected $reputation;

    /**
     * @var ActivityRepository
     */
    protected $activity;

    /**
     * @var TopicRepository
     */
    protected $topic;

    /**
     * @var WikiRepository
     */
    protected $wiki;

    /**
     * @param MicroblogRepository $microblog
     * @param ReputationRepository $reputation
     * @param ActivityRepository $activity
     * @param TopicRepository $topic
     * @param WikiRepository $wiki
     */
    public function __construct(
        MicroblogRepository $microblog,
        ReputationRepository $reputation,
        ActivityRepository $activity,
        TopicRepository $topic,
        WikiRepository $wiki
    ) {
        parent::__construct();

        $this->microblog = $microblog;
        $this->reputation = $reputation;
        $this->activity = $activity;
        $this->topic = $topic;
        $this->wiki = $wiki;
    }

    /**
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $result = [];
        $reflection = new \ReflectionClass($this);

        $cache = $this->getCacheFactory();

        $this->topic->pushCriteria(new OnlyThoseTopicsWithAccess());

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PRIVATE) as $method) {
            $method = $method->name;
            $snake = snake_case($method);

            if (substr($snake, 0, 3) === 'get') {
                $name = substr($snake, 4);

                if (in_array($name, ['reputation', 'newest', 'voted', 'interesting', 'blog', 'patronage'])) {
                    $result[$name] = $cache->remember('homepage:' . $name, 30, function () use ($method) {
                        return $this->$method();
                    });
                } else {
                    $result[$name] = $this->$method();
                }
            }
        }

        $this->request->attributes->set('settings_url', route('user.settings.ajax', [], false));

        return $this->view('home', $result)->with('settings', $this->getSettings());
    }

    /**
     * @return array
     */
    private function getReputation()
    {
        return [
            'month'   => $this->reputation->monthly(),
            'year'    => $this->reputation->yearly(),
            'total'   => $this->reputation->total()
        ];
    }

    /**
     * @return mixed
     */
    private function getBlog()
    {
        /** @var \Coyote\Wiki $parent */
        $parent = $this->wiki->findByPath('Blog');
        if (!$parent) {
            return [];
        }

        return $parent->children()->latest()->limit(5)->get(['created_at', 'path', 'title', 'long_title']);
    }

    /**
     * @return mixed
     */
    private function getMicroblogs()
    {
        $this->microblog->pushCriteria(new LoadComments($this->userId));
        $this->microblog->pushCriteria(new OrderByScore());

        return $this->slice($this->microblog->take(5));
    }

    /**
     * @return mixed
     */
    private function getVoted()
    {
        return $this->topic->voted();
    }

    /**
     * @return mixed
     */
    private function getNewest()
    {
        return $this->topic->newest();
    }

    /**
     * @return mixed
     */
    private function getInteresting()
    {
        return $this->topic->interesting();
    }

    /**
     * @return array
     */
    private function getActivities()
    {
        $this->activity->pushCriteria(new OnlyThoseForumsWithAccess($this->auth));
        $this->activity->pushCriteria(new SkipHiddenCategories($this->userId));

        $this->activity->pushCriteria(new EagerLoading(['topic', 'content', 'forum']));
        $this->activity->pushCriteria(new EagerLoading(['topic' => function (BelongsTo $query) {
            $query->withTrashed();
        }]));

        $this->activity->pushCriteria(new EagerLoading(['user' => function (BelongsTo $query) {
            $query->withTrashed();
        }]));

        $result = $this->activity->latest(20);

        return ActivityResource::collection($result)->toArray($this->request);
    }

    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    private function getViewers()
    {
        /** @var Renderer $viewers */
        $viewers = app(Renderer::class);
        return $viewers->render();
    }

    /**
     * @return array
     */
    private function getPatronage()
    {
        /** @var \Coyote\Wiki $parent */
        $parent = $this->wiki->findByPath('Patronat');
        if (!$parent) {
            return [];
        }

        return $parent->children()->latest()->limit(1)->first(['path', 'title', 'excerpt']);
    }

    /**
     * Zostawia jedynie 2 ostatnie komentarze do wpisu
     *
     * @param $microblogs
     * @return mixed
     */
    private function slice($microblogs)
    {
        foreach ($microblogs as &$microblog) {
            $microblog->comments_count = $microblog->comments->count();
            $microblog->comments = $microblog->comments->slice(-2, 2);
        }

        return $microblogs;
    }
}
